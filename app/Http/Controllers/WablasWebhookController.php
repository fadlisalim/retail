<?php

namespace App\Http\Controllers;

use App\Models\WaMessage;
use App\Services\WaCampaign\WablasCampaignClient;
use App\Services\WaCampaign\WaCampaignService;
use App\Services\WaCampaign\WaContactService;
use App\Services\WhatsAppService;
use App\Support\WablasMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
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

        // Produksi tanpa WABLAS_WEBHOOK_TOKEN = siapa pun bisa menyuntikkan
        // "pesan masuk" palsu ke inbox. Tolak, dan ingatkan lewat log.
        if ($expected === '' && config('app.env') === 'production') {
            if (Cache::add('wablas_webhook_no_token_warned', 1, 3600)) {
                Log::warning('Webhook Wablas ditolak: WABLAS_WEBHOOK_TOKEN belum diisi di .env — isi token lalu tambahkan ?token=... di URL webhook Wablas.');
            }

            return response('', 403);
        }

        if ($expected !== '' && ! hash_equals($expected, (string) ($request->query('token') ?: $request->header('X-Webhook-Token')))) {
            Log::warning('Webhook Wablas ditolak: token salah', ['ip' => $request->ip()]);

            return response('', 403);
        }

        $payload = $request->all();
        // Some setups post a raw JSON body without form encoding.
        if (empty($payload)) {
            $payload = json_decode($request->getContent(), true) ?: [];
        }

        // Ignore group chats and payloads without a sender phone.
        if (($payload['isGroup'] ?? false) === true || ($payload['isGroup'] ?? null) === 'true') {
            return $this->done($payload, 'grup — diabaikan');
        }

        $phone = $wa->normalize((string) ($payload['phone'] ?? $payload['sender'] ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));

        // Webhook tracking status pesan keluar (ack: sent/delivered/read/cancel/reject).
        // Bila id cocok dengan pesan campaign, terapkan apa pun bentuk payload-nya
        // (beberapa server Wablas ikut mengirim teks pesan). Idempotent, status tidak mundur.
        $ack = strtolower((string) ($payload['status'] ?? $payload['ack'] ?? ''));
        if (isset($payload['id']) && $ack !== '') {
            if (app(WaCampaignService::class)->applyStatus((string) $payload['id'], $ack, isset($payload['note']) ? (string) $payload['note'] : null)) {
                return $this->done($payload, 'status campaign: '.$ack);
            }
            if ($message === '' && ! isset($payload['pushName'])) {
                return $this->done($payload, 'status pesan non-campaign: '.$ack);
            }
        }

        // "STOP" dari pelanggan = berhenti promo: kontak masuk daftar pengecualian,
        // antreannya dibatalkan (termasuk pending di Wablas bila didukung), lalu
        // konfirmasi singkat dikirim sekali. Dedupe via wablas_id di bawah tetap berlaku
        // untuk inbox; untuk STOP, opt-out berulang tidak berbahaya (idempotent).
        $fromMe = filter_var($payload['fromMe'] ?? $payload['from_me'] ?? $payload['isFromMe'] ?? false, FILTER_VALIDATE_BOOL);
        $handled = $fromMe ? 'pesan keluar dari HP' : 'pesan masuk';
        if ($phone && ! $fromMe && $message !== '') {
            $contacts = app(WaContactService::class);
            if ($contacts->isStopMessage($message)) {
                $handled = 'STOP — kontak berhenti promo';
                $contact = $contacts->upsert($phone, ['name' => $payload['pushName'] ?? null, 'source' => 'wachat']);
                if ($contact && ! $contact->isOptedOut()) {
                    $contacts->optOut($contact, 'Balas STOP', app(WablasCampaignClient::class));
                    $wa->send($phone, 'Baik, nomor ini tidak akan menerima promo dari '.brand().' lagi. Terima kasih 🙏');
                }
            } else {
                $campaigns = app(WaCampaignService::class);
                $campaigns->noteReply($phone);
                if ($campaigns->handlePermissionReply($phone, $message)) {
                    $handled = 'YA — izin promo tercatat, pesan lanjutan dikirim';
                }
            }
        }

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
            return $this->done($payload, 'tanpa nomor/isi — diabaikan');
        }

        $wablasId = isset($payload['id']) ? Str::limit((string) $payload['id'], 100, '') : null;
        if ($wablasId && WaMessage::where('wablas_id', $wablasId)->exists()) {
            return $this->done($payload, $handled.' (duplikat)');
        }

        // Messages sent from the device phone itself (admin replying on the HP)
        // are webhooked by some Wablas setups with a fromMe flag — recorded as
        // outgoing so the thread mirrors WhatsApp correctly ($fromMe di atas).

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

        return $this->done($payload, $handled);
    }

    public const RECENT_KEY = 'wablas:webhook:recent';

    /**
     * Catat ringkasan webhook terakhir (maks. 15) untuk diagnosis di Admin → WA Campaign →
     * Pengaturan: apakah tracking status benar-benar masuk dan bagaimana bentuknya.
     * IMPORTANT: respond with an EMPTY body. Per the Wablas webhook docs, any text
     * echoed back is auto-sent to the customer as a reply.
     */
    private function done(array $payload, string $handled): Response
    {
        try {
            $recent = Cache::get(self::RECENT_KEY, []);
            array_unshift($recent, [
                'at' => now()->toIso8601String(),
                'handled' => $handled,
                'keys' => implode(', ', array_slice(array_keys($payload), 0, 12)),
                'id' => isset($payload['id']) ? Str::limit((string) $payload['id'], 40, '…') : null,
                'status' => isset($payload['status']) || isset($payload['ack']) ? Str::limit((string) ($payload['status'] ?? $payload['ack']), 20, '') : null,
                'phone' => isset($payload['phone']) || isset($payload['sender']) ? Str::mask((string) ($payload['phone'] ?? $payload['sender']), '*', 4, -3) : null,
                'message' => isset($payload['message']) ? Str::limit(trim((string) $payload['message']), 50) : null,
            ]);
            Cache::put(self::RECENT_KEY, array_slice($recent, 0, 15), 7 * 86400);
        } catch (\Throwable) {
            // diagnosis saja — jangan pernah menggagalkan webhook
        }

        return response('', 200);
    }
}
