<?php

namespace App\Http\Controllers;

use App\Models\AssistantLead;
use App\Models\Product;
use App\Models\SiteChatMessage;
use App\Services\AssistantAnalytics;
use App\Services\SettingService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Storefront side of "Chat Toko" — the Tokopedia-style chat with a human
 * admin (NOT the AI assistant). Customers post messages (optionally with a
 * product attached) and poll for admin replies. Conversations are keyed by
 * the same per-browser session id the CS assistant uses, so a guest's
 * name/phone from assistant_leads identifies them here too. Guests must
 * leave a name + WA number with their first message; logged-in customers
 * are identified by their account.
 */
class SiteChatController extends Controller
{
    /** Poll messages (and read-state bookkeeping) for this browser session. */
    public function messages(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'string', 'max:64'],
            'after_id' => ['sometimes', 'integer', 'min:0'],
        ]);
        $sessionId = $this->cleanSession($data['session_id']);
        if ($sessionId === '') {
            return response()->json(['messages' => [], 'need_contact' => true]);
        }

        $messages = SiteChatMessage::where('session_id', $sessionId)
            ->where('id', '>', (int) ($data['after_id'] ?? 0))
            ->orderBy('id')
            ->limit(100)
            ->get();

        // Admin replies delivered to this poll are now read by the customer.
        SiteChatMessage::where('session_id', $sessionId)
            ->where('direction', 'out')->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'messages' => $this->payload($messages),
            'need_contact' => $this->needsContact($request, $sessionId),
        ]);
    }

    /** Send a customer message to the store inbox. */
    public function send(Request $request, AssistantAnalytics $analytics, WhatsAppService $wa): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'string', 'max:64'],
            'message' => ['required', 'string', 'max:2000'],
            'product_slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);
        $sessionId = $this->cleanSession($data['session_id']);
        abort_if($sessionId === '', 422);

        // First message from a guest must carry name + WA number (Tokopedia
        // requires an account; we ask for contact data instead).
        if (! empty($data['name']) || ! empty($data['phone'])) {
            $phone = ! empty($data['phone']) ? $wa->normalize($data['phone']) : null;
            if (! empty($data['phone']) && ! $phone) {
                return response()->json(['ok' => false, 'error' => 'Nomor WhatsApp tidak valid — cek lagi ya (contoh: 0812xxxxxxx).'], 422);
            }
            $analytics->captureLead(array_filter(['name' => trim((string) $data['name']), 'phone' => $phone]), $sessionId);
        }
        if ($this->needsContact($request, $sessionId)) {
            return response()->json(['ok' => false, 'need_contact' => true, 'error' => 'Isi nama & nomor WhatsApp dulu ya, biar admin bisa membalas.'], 422);
        }

        $productSlug = null;
        $productName = null;
        if (! empty($data['product_slug'])) {
            $focus = Product::published()->where('slug', $data['product_slug'])->first(['slug', 'name']);
            $productSlug = $focus?->slug;
            $productName = $focus?->name;
        }

        // Notify the admin's WA only when this message STARTS a new unread
        // burst — while earlier messages sit unread, more pings help nobody.
        $adminCaughtUp = ! SiteChatMessage::where('session_id', $sessionId)
            ->where('direction', 'in')->where('is_read', false)->exists();

        $row = SiteChatMessage::create([
            'session_id' => $sessionId,
            'user_id' => $request->user()?->id,
            'direction' => 'in',
            'message' => Str::limit(trim($data['message']), 2000),
            'product_slug' => $productSlug,
            'is_read' => false,
            'created_at' => now(),
        ]);

        if ($adminCaughtUp) {
            $this->notifyAdmin($request, $sessionId, $row, $productName);
        }

        return response()->json(['ok' => true, 'id' => $row->id]);
    }

    /**
     * Internal WA ping (via Wablas) to the store's admin number when a new
     * web chat comes in, so nobody has to keep the admin page open. Guarded:
     * only for messages that start a new unread burst, plus a 10-minute
     * per-session cooldown. Sent after the response so the customer never
     * waits on the gateway; failures are logged inside WhatsAppService.
     */
    private function notifyAdmin(Request $request, string $sessionId, SiteChatMessage $row, ?string $productName): void
    {
        $settings = app(SettingService::class);
        $wa = app(WhatsAppService::class);

        $adminNumber = trim((string) $settings->get('whatsapp.admin_notify'));
        if ($adminNumber === '' || ! $wa->isEnabled()) {
            return;
        }

        // Cooldown: at most one ping per session per 10 minutes.
        if (! Cache::add('sitechat_notif_'.$sessionId, 1, 600)) {
            return;
        }

        $lead = AssistantLead::where('session_id', $sessionId)->first();
        $who = $request->user()?->name ?: ($lead?->name ?: 'Pengunjung');
        $phone = $lead?->phone ? ' ('.$lead->phone.')' : '';

        $text = '💬 Chat Toko baru dari '.$who.$phone
            .($productName ? "\nProduk: ".$productName : '')
            ."\nPesan: \"".Str::limit($row->message, 120)."\""
            ."\nBalas: ".route('admin.sitechat.index', ['sesi' => $sessionId]);

        // Idempotency key: terminating callbacks are never pruned by the
        // framework, so in long-lived processes (tests, Octane) this closure
        // can be re-invoked on LATER requests' terminate — the key makes any
        // replay a no-op instead of a duplicate WA ping.
        $sentKey = 'sitechat_notif_sent_'.$row->id;
        dispatch(function () use ($wa, $adminNumber, $text, $sentKey) {
            if (Cache::add($sentKey, 1, 3600)) {
                $wa->send($adminNumber, $text);
            }
        })->afterResponse();
    }

    /**
     * Lightweight unread counter for the header bell (Tokopedia-style chat
     * notification): admin replies not yet delivered to this browser session.
     * Read-only — the badge clears when the widget actually loads them.
     */
    public function unread(Request $request): JsonResponse
    {
        $data = $request->validate(['session_id' => ['required', 'string', 'max:64']]);
        $sessionId = $this->cleanSession($data['session_id']);

        $unread = $sessionId === '' ? 0 : SiteChatMessage::where('session_id', $sessionId)
            ->where('direction', 'out')->where('is_read', false)->count();

        return response()->json(['unread' => $unread]);
    }

    /** Guests need a lead (name + phone) on file before the chat opens. */
    private function needsContact(Request $request, string $sessionId): bool
    {
        if ($request->user()) {
            return false;
        }

        $lead = AssistantLead::where('session_id', $sessionId)->first();

        return ! ($lead && filled($lead->name) && filled($lead->phone));
    }

    /** JSON payload for a set of messages, product cards resolved. */
    private function payload($messages): array
    {
        $slugs = $messages->pluck('product_slug')->filter()->unique()->values();
        $products = $slugs->isEmpty()
            ? collect()
            : Product::published()->whereIn('slug', $slugs)->get()->keyBy('slug');

        return $messages->map(function (SiteChatMessage $m) use ($products) {
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
        })->all();
    }

    private function cleanSession(string $raw): string
    {
        return Str::limit(preg_replace('/[^A-Za-z0-9_-]/', '', $raw), 64, '');
    }
}
