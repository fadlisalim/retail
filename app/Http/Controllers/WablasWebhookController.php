<?php

namespace App\Http\Controllers;

use App\Models\WaMessage;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function __invoke(Request $request, WhatsAppService $wa): JsonResponse
    {
        $expected = (string) config('services.wablas.webhook_token');
        if ($expected !== '' && ! hash_equals($expected, (string) ($request->query('token') ?: $request->header('X-Webhook-Token')))) {
            return response()->json(['ok' => false, 'error' => 'invalid token'], 403);
        }

        $payload = $request->all();
        // Some setups post a raw JSON body without form encoding.
        if (empty($payload)) {
            $payload = json_decode($request->getContent(), true) ?: [];
        }

        // Ignore group chats and payloads without a sender phone.
        if (($payload['isGroup'] ?? false) === true || ($payload['isGroup'] ?? null) === 'true') {
            return response()->json(['ok' => true, 'ignored' => 'group']);
        }

        $phone = $wa->normalize((string) ($payload['phone'] ?? $payload['sender'] ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));
        $file = trim((string) ($payload['file'] ?? $payload['url'] ?? ''));
        if ($message === '' && $file !== '') {
            $message = '[media] '.$file;
        }

        if (! $phone || $message === '') {
            return response()->json(['ok' => true, 'ignored' => 'not a message']);
        }

        $wablasId = isset($payload['id']) ? Str::limit((string) $payload['id'], 100, '') : null;
        if ($wablasId && WaMessage::where('wablas_id', $wablasId)->exists()) {
            return response()->json(['ok' => true, 'ignored' => 'duplicate']);
        }

        try {
            WaMessage::create([
                'phone' => $phone,
                'name' => isset($payload['pushName']) ? Str::limit((string) $payload['pushName'], 255, '') : null,
                'direction' => 'in',
                'message' => Str::limit($message, 4000),
                'wablas_id' => $wablasId,
                'is_read' => false,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Unique-collision race on retries etc. — acknowledge so Wablas stops retrying.
            Log::warning('Wablas webhook store failed: '.$e->getMessage());
        }

        return response()->json(['ok' => true]);
    }
}
