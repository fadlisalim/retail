<?php

namespace App\Http\Controllers;

use App\Models\WaMessage;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives incoming-message webhooks from Wablas (configure the URL in the
 * Wablas device settings). CSRF-exempt; guarded by a shared token when
 * WABLAS_WEBHOOK_TOKEN is set (append ?token=... to the URL in Wablas).
 * Payload shapes vary across Wablas versions, so parsing is liberal: we only
 * need phone + message (+ optional pushName / id / file). Non-message payloads
 * (device status etc.) are acknowledged and ignored.
 */
class WablasWebhookController extends Controller
{
    public function __invoke(Request $request, WhatsAppService $wa): Response
    {
        $expected = (string) config('services.wablas.webhook_token');
        if ($expected !== '' && ! hash_equals($expected, (string) ($request->query('token') ?: $request->header('X-Webhook-Token')))) {
            return response('', 403);
        }

        $payload = $request->all();
        // Some setups post a raw JSON body without form encoding.
        if (empty($payload)) {
            $payload = json_decode($request->getContent(), true) ?: [];
        }

        // Ignore group chats and payloads without a sender phone.
        if (($payload['isGroup'] ?? false) === true || ($payload['isGroup'] ?? null) === 'true') {
            return response('', 200);
        }

        $phone = $wa->normalize((string) ($payload['phone'] ?? $payload['sender'] ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));

        // Media arrives either as a full URL or as a bare stored filename,
        // depending on the Wablas server. Keep it as media (not text) so the
        // inbox can show a thumbnail instead of "[media] abc.jpeg".
        $file = trim((string) ($payload['file'] ?? $payload['url'] ?? ''));
        $mediaUrl = $this->mediaUrl($file);
        $mediaType = $this->mediaType((string) ($payload['messageType'] ?? $payload['type'] ?? ''), $file);

        if (! $phone || ($message === '' && $mediaUrl === null)) {
            return response('', 200);
        }

        $wablasId = isset($payload['id']) ? Str::limit((string) $payload['id'], 100, '') : null;
        if ($wablasId && WaMessage::where('wablas_id', $wablasId)->exists()) {
            return response('', 200);
        }

        // Messages sent from the device phone itself (admin replying on the HP)
        // are webhooked by some Wablas setups with a fromMe flag — record those
        // as outgoing so the thread mirrors WhatsApp correctly.
        $fromMe = filter_var($payload['fromMe'] ?? $payload['from_me'] ?? $payload['isFromMe'] ?? false, FILTER_VALIDATE_BOOL);

        try {
            WaMessage::create([
                'phone' => $phone,
                'name' => $fromMe ? null : (isset($payload['pushName']) ? Str::limit((string) $payload['pushName'], 255, '') : null),
                'direction' => $fromMe ? 'out' : 'in',
                'is_read' => $fromMe,
                'sent_ok' => $fromMe ? true : null,
                'message' => Str::limit($message, 4000),
                'media_url' => $mediaUrl,
                'media_type' => $mediaUrl ? $mediaType : null,
                'media_name' => $mediaUrl ? Str::limit(basename(parse_url($file, PHP_URL_PATH) ?: $file), 191, '') : null,
                'wablas_id' => $wablasId,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Unique-collision race on retries etc. — acknowledge so Wablas stops retrying.
            Log::warning('Wablas webhook store failed: '.$e->getMessage());
        }

        // IMPORTANT: respond with an EMPTY body. Per the Wablas webhook docs,
        // any text echoed back is auto-sent to the customer as a reply — a JSON
        // body here would land in the customer's WhatsApp as garbage.
        return response('', 200);
    }

    /**
     * Absolute URL for an incoming media file. Wablas may send a full URL or
     * just the stored filename — the latter needs WABLAS_MEDIA_BASE_URL to be
     * resolvable, otherwise we keep the raw value so nothing is lost.
     */
    private function mediaUrl(string $file): ?string
    {
        if ($file === '') {
            return null;
        }

        if (Str::startsWith($file, ['http://', 'https://'])) {
            return Str::limit($file, 500, '');
        }

        $base = trim((string) config('services.wablas.media_base_url'));

        return $base === ''
            ? Str::limit($file, 500, '')            // filename only — shown as a label
            : Str::limit(rtrim($base, '/').'/'.ltrim($file, '/'), 500, '');
    }

    /** Normalise Wablas' message type, falling back to the file extension. */
    private function mediaType(string $reported, string $file): string
    {
        $reported = mb_strtolower(trim($reported));
        foreach (['image', 'video', 'audio', 'document'] as $type) {
            if (str_contains($reported, $type)) {
                return $type;
            }
        }

        $ext = mb_strtolower(pathinfo(parse_url($file, PHP_URL_PATH) ?: $file, PATHINFO_EXTENSION));

        return match (true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) => 'image',
            in_array($ext, ['mp4', 'mkv', '3gp', 'mov'], true) => 'video',
            in_array($ext, ['mp3', 'ogg', 'opus', 'wav', 'm4a'], true) => 'audio',
            default => 'document',
        };
    }
}
