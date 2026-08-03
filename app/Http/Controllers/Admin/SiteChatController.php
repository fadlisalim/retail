<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssistantLead;
use App\Models\Product;
use App\Models\SiteChatMessage;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin inbox for the on-site "Chat Toko" (customer ↔ admin, Tokopedia-style):
 * conversations grouped by browser session; incoming messages arrive from the
 * storefront widget, replies are stored and picked up by the customer's poll.
 */
class SiteChatController extends Controller
{
    /** Conversation list + (optionally) one open thread. */
    public function index(Request $request): View
    {
        $session = Str::limit(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $request->query('sesi')), 64, '') ?: null;

        $conversations = SiteChatMessage::query()
            ->select('session_id')
            ->selectRaw('MAX(created_at) as last_at')
            ->selectRaw("SUM(CASE WHEN direction = 'in' AND is_read = 0 THEN 1 ELSE 0 END) as unread")
            ->groupBy('session_id')
            ->orderByRaw('MAX(created_at) DESC')
            ->limit(100)
            ->get();

        $sessionIds = $conversations->pluck('session_id');

        // Display names: customer account name, else lead name (guest form).
        $userNames = SiteChatMessage::whereIn('session_id', $sessionIds)
            ->whereNotNull('user_id')
            ->join('users', 'users.id', '=', 'site_chat_messages.user_id')
            ->orderBy('site_chat_messages.created_at')
            ->pluck('users.name', 'site_chat_messages.session_id');
        $leads = AssistantLead::whereIn('session_id', $sessionIds)->get()->keyBy('session_id');

        $previews = SiteChatMessage::whereIn('session_id', $sessionIds)
            ->orderByDesc('id')->get(['session_id', 'message', 'direction'])
            ->unique('session_id');

        $thread = collect();
        $threadProducts = collect();
        if ($session) {
            SiteChatMessage::where('session_id', $session)->where('direction', 'in')->where('is_read', false)->update(['is_read' => true]);
            $thread = SiteChatMessage::where('session_id', $session)->orderBy('created_at')->orderBy('id')->limit(300)->get();
            $slugs = $thread->pluck('product_slug')->filter()->unique()->values();
            $threadProducts = $slugs->isEmpty() ? collect() : Product::whereIn('slug', $slugs)->get()->keyBy('slug');
        }

        return view('admin.site-chat', [
            'conversations' => $conversations,
            'userNames' => $userNames,
            'leads' => $leads,
            'previews' => $previews->keyBy('session_id'),
            'session' => $session,
            'thread' => $thread,
            'threadProducts' => $threadProducts,
        ]);
    }

    /** JSON poll: new messages in a thread after a given id (marks them read). */
    public function messages(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sesi' => ['required', 'string', 'max:64'],
            'after_id' => ['sometimes', 'integer', 'min:0'],
        ]);
        $session = Str::limit(preg_replace('/[^A-Za-z0-9_-]/', '', $data['sesi']), 64, '');
        abort_if($session === '', 422);

        $messages = SiteChatMessage::where('session_id', $session)
            ->where('id', '>', (int) ($data['after_id'] ?? 0))
            ->orderBy('id')
            ->limit(100)
            ->get();

        SiteChatMessage::where('session_id', $session)->where('direction', 'in')->where('is_read', false)->update(['is_read' => true]);

        $slugs = $messages->pluck('product_slug')->filter()->unique()->values();
        $products = $slugs->isEmpty() ? collect() : Product::whereIn('slug', $slugs)->get()->keyBy('slug');

        return response()->json([
            'messages' => $messages->map(function (SiteChatMessage $m) use ($products) {
                $p = $m->product_slug ? ($products[$m->product_slug] ?? null) : null;

                return [
                    'id' => $m->id,
                    'direction' => $m->direction,
                    'message' => $m->message,
                    'time' => $m->created_at?->format('H:i'),
                    'date' => $m->created_at?->format('d/m/Y'),
                    'notified' => (bool) $m->notified_at,
                    'product' => $p ? [
                        'name' => $p->name,
                        'url' => route('products.show', $p->slug),
                        'price' => rupiah($p->effectivePrice()),
                        'image' => $p->primaryImageUrl(),
                    ] : null,
                ];
            }),
        ]);
    }

    /** Store an admin reply; the customer's widget picks it up on poll. */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sesi' => ['required', 'string', 'max:64'],
            'message' => ['required', 'string', 'max:2000'],
        ]);
        $session = Str::limit(preg_replace('/[^A-Za-z0-9_-]/', '', $data['sesi']), 64, '');
        abort_if($session === '', 422);

        $row = SiteChatMessage::create([
            'session_id' => $session,
            'admin_id' => $request->user()?->id,
            'direction' => 'out',
            'message' => Str::limit(trim($data['message']), 2000),
            'is_read' => false,
            'created_at' => now(),
        ]);

        // True = a WhatsApp ping was queued for this reply (the actual send
        // happens after the response, so notified_at is set a moment later).
        $notified = $this->notifyCustomer($row);

        return response()->json(['ok' => true, 'id' => $row->id, 'notified' => $notified]);
    }

    /**
     * Ping the customer on WhatsApp that the admin replied — at most ONCE per
     * calendar day per conversation, however many replies are sent. The daily
     * mark lives in the database (notified_at) so it survives cache clears;
     * a short cache lock only guards against two rapid replies racing.
     * The message is a nudge back to the website, not a copy of the thread,
     * so the conversation stays in one place.
     *
     * @return bool whether a notification was queued for this reply
     */
    private function notifyCustomer(SiteChatMessage $row): bool
    {
        $wa = app(WhatsAppService::class);
        if (! $wa->isEnabled()) {
            return false;
        }

        // Already notified today for this conversation?
        $alreadyToday = SiteChatMessage::where('session_id', $row->session_id)
            ->whereNotNull('notified_at')
            ->whereDate('notified_at', now()->toDateString())
            ->exists();
        if ($alreadyToday) {
            return false;
        }

        [$phone, $name] = $this->customerContact($row->session_id);
        if (! $phone) {
            return false;
        }

        // Race guard until midnight (cheap; DB check above is the durable one).
        if (! Cache::add('sitechat_cust_notif_'.$row->session_id.'_'.now()->toDateString(), 1, now()->endOfDay())) {
            return false;
        }

        $text = 'Halo'.($name ? ' Kak '.$name : ' Kak').' 👋'."\n"
            .'Admin '.brand().' sudah membalas chat Kakak di website.'."\n\n"
            .'Balasan: "'.Str::limit($row->message, 160).'"'."\n\n"
            .'Buka '.rtrim((string) config('app.url'), '/').' lalu klik ikon chat untuk melanjutkan percakapan ya 😊';

        $rowId = $row->id;
        dispatch(function () use ($wa, $phone, $text, $rowId) {
            // Terminating callbacks are never pruned by the framework, so this
            // closure can be replayed on a LATER request in long-lived
            // processes (tests, Octane). Re-reading the row makes a replay a
            // no-op instead of a duplicate WhatsApp message.
            $fresh = SiteChatMessage::find($rowId);
            if (! $fresh || $fresh->notified_at) {
                return;
            }

            if ($wa->send($phone, $text)) {
                // Mark only on success, so a failed gateway retries on the
                // next reply instead of silently skipping the day.
                $fresh->forceFill(['notified_at' => now()])->save();
            }
        })->afterResponse();

        return true;
    }

    /** Customer's WhatsApp number + name: account first, else chat lead. */
    private function customerContact(string $sessionId): array
    {
        $userId = SiteChatMessage::where('session_id', $sessionId)->whereNotNull('user_id')->value('user_id');
        if ($userId && ($user = User::find($userId))) {
            $phone = app(WhatsAppService::class)->normalize($user->whatsapp ?? $user->phone);
            if ($phone) {
                return [$phone, $user->name];
            }
        }

        $lead = AssistantLead::where('session_id', $sessionId)->first();

        return [$lead?->phone ? app(WhatsAppService::class)->normalize($lead->phone) : null, $lead?->name];
    }

    /** Fallback label for guests without a name. */
    public static function guestLabel(string $sessionId): string
    {
        return 'Pengunjung #'.strtoupper(substr($sessionId, 0, 6));
    }
}
