<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssistantLead;
use App\Models\Product;
use App\Models\SiteChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json(['ok' => true, 'id' => $row->id]);
    }

    /** Fallback label for guests without a name. */
    public static function guestLabel(string $sessionId): string
    {
        return 'Pengunjung #'.strtoupper(substr($sessionId, 0, 6));
    }
}
