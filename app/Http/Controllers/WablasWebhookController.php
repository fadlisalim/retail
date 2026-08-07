<?php

namespace App\Http\Controllers;

use App\Models\WaMessage;
use App\Services\WhatsAppService;
use App\Support\WablasMedia;
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

        // Fallback: some servers omit `file` entirely and put the media URL in
        // the message body. Promote it so the attachment renders instead of
        // sitting in the thread as a naked link.
        if ($file === '' && ($fromBody = WablasMedia::fromText($message))) {
            $file = $fromBody;
            $message = '';
        }

        $mediaUrl = WablasMedia::url($file);
        $mediaType = WablasMedia::type((string) ($payload['messageType'] ?? $payload['type'] ?? ''), $file);

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
                'media_name' => $mediaUrl ? WablasMedia::name($file) : null,
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

}
