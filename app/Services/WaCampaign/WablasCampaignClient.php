<?php

namespace App\Services\WaCampaign;

use App\Services\WhatsAppService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Transport ke Wablas untuk campaign. Kredensial hanya dari .env
 * (WABLAS_BASE_URL, WABLAS_TOKEN, WABLAS_SECRET) — tidak pernah di DB/view.
 * TLS diverifikasi (default Laravel HTTP client). Mode MOCK aktif bila
 * WABLAS_CAMPAIGN_MOCK=true atau Wablas belum diaktifkan: tidak ada request
 * keluar, pesan "diterima" dengan id MOCK-… supaya alur bisa diuji tanpa
 * mengirim ke customer nyata.
 *
 * Hasil send(): ['ok'=>bool, 'uncertain'=>bool, 'id'=>?string, 'error'=>?string, 'retryable'=>bool].
 * uncertain = request timeout / koneksi putus setelah request terkirim — pesan
 * MUNGKIN sudah terkirim, jadi TIDAK boleh dikirim ulang otomatis.
 */
class WablasCampaignClient
{
    public function __construct(private readonly WhatsAppService $wa) {}

    public function isMock(): bool
    {
        return (bool) config('services.wablas.campaign_mock') || ! $this->wa->isEnabled();
    }

    /** @return array{ok: bool, uncertain: bool, id: ?string, error: ?string, retryable: bool, raw: mixed} */
    public function sendText(string $phone, string $message): array
    {
        return $this->send('/api/v2/send-message', ['phone' => $phone, 'message' => $message, 'isGroup' => 'false']);
    }

    /** @return array{ok: bool, uncertain: bool, id: ?string, error: ?string, retryable: bool, raw: mixed} */
    public function sendImage(string $phone, string $imageUrl, string $caption): array
    {
        return $this->send('/api/v2/send-image', ['phone' => $phone, 'image' => $imageUrl, 'caption' => $caption, 'isGroup' => 'false']);
    }

    /**
     * Batalkan pesan yang masih pending di Wablas (bila server mendukung).
     * Best-effort: kegagalan hanya dicatat, status lokal tetap dibatalkan.
     */
    public function cancel(string $wablasId): bool
    {
        if ($this->isMock()) {
            return true;
        }
        try {
            $res = Http::withHeaders($this->headers())->asJson()->timeout(15)
                ->post($this->endpoint(config('services.wablas.cancel_path', '/api/v2/cancel-message')), ['data' => [['id' => $wablasId]]]);

            return $res->successful() && filter_var($res->json('status'), FILTER_VALIDATE_BOOL);
        } catch (\Throwable $e) {
            Log::info('Wablas cancel gagal: '.$e->getMessage(), ['id' => $wablasId]);

            return false;
        }
    }

    /**
     * Info device: status koneksi + kuota + jenis (bila disediakan server).
     *
     * @return array{connected: ?bool, status: ?string, quota: ?int, raw: mixed, error: ?string}
     */
    public function deviceInfo(): array
    {
        if ($this->isMock()) {
            return ['connected' => true, 'status' => 'mock', 'quota' => null, 'raw' => null, 'error' => null];
        }
        try {
            $res = Http::withHeaders($this->headers())->timeout(15)->get($this->endpoint('/api/device/info'), ['token' => config('services.wablas.token')]);
            $data = (array) ($res->json('data') ?? []);
            $status = strtolower((string) ($data['status'] ?? $data['device_status'] ?? ''));

            return [
                'connected' => $status !== '' ? in_array($status, ['connected', 'online', 'ready', 'true'], true) : null,
                'status' => $status ?: null,
                'quota' => isset($data['quota']) ? (int) $data['quota'] : null,
                'raw' => $data,
                'error' => $res->successful() ? null : 'HTTP '.$res->status(),
            ];
        } catch (\Throwable $e) {
            return ['connected' => null, 'status' => null, 'quota' => null, 'raw' => null, 'error' => $e->getMessage()];
        }
    }

    private function send(string $path, array $row): array
    {
        if ($this->isMock()) {
            $id = 'MOCK-'.Str::upper(Str::random(10));
            Log::info('[WA Campaign MOCK] kirim', ['phone' => $row['phone'], 'path' => $path, 'id' => $id]);

            return ['ok' => true, 'uncertain' => false, 'id' => $id, 'error' => null, 'retryable' => false, 'raw' => ['mock' => true]];
        }

        try {
            $res = Http::withHeaders($this->headers())->asJson()->timeout(20)->post($this->endpoint($path), ['data' => [$row]]);
        } catch (ConnectionException $e) {
            // Timeout/putus: tidak tahu apakah Wablas sempat menerima → uncertain, jangan kirim ulang otomatis.
            $msg = $e->getMessage();
            $uncertain = (bool) preg_match('/timed out|timeout|operation timed out|SSL read|recv failure|empty reply/i', $msg);

            return ['ok' => false, 'uncertain' => $uncertain, 'id' => null, 'error' => Str::limit($msg, 300), 'retryable' => ! $uncertain, 'raw' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'uncertain' => false, 'id' => null, 'error' => Str::limit($e->getMessage(), 300), 'retryable' => false, 'raw' => null];
        }

        $json = $res->json() ?? [];
        $status = filter_var($json['status'] ?? false, FILTER_VALIDATE_BOOL);
        if ($res->successful() && $status) {
            $first = (array) (data_get($json, 'data.messages.0') ?? data_get($json, 'data.0') ?? []);
            $id = isset($first['id']) ? (string) $first['id'] : null;
            $msgStatus = strtolower((string) ($first['status'] ?? ''));
            if (in_array($msgStatus, ['reject', 'rejected', 'failed', 'invalid'], true)) {
                return ['ok' => false, 'uncertain' => false, 'id' => $id, 'error' => 'Ditolak gateway: '.($first['message'] ?? $msgStatus), 'retryable' => false, 'raw' => $json];
            }

            return ['ok' => true, 'uncertain' => false, 'id' => $id, 'error' => null, 'retryable' => false, 'raw' => $json];
        }

        $error = Str::limit((string) ($json['message'] ?? $res->body()), 300);
        $retryable = $res->status() >= 500 || $res->status() === 429;
        if ($res->status() === 401 || $res->status() === 403) {
            $error = 'Token Wablas ditolak ('.$res->status().') — periksa WABLAS_TOKEN/SECRET.';
        }

        return ['ok' => false, 'uncertain' => false, 'id' => null, 'error' => $error ?: 'HTTP '.$res->status(), 'retryable' => $retryable, 'raw' => $json];
    }

    private function headers(): array
    {
        $token = (string) config('services.wablas.token');
        $secret = trim((string) config('services.wablas.secret'));

        // Wablas: Authorization = token, atau token.secret bila akun memakai secret key.
        return ['Authorization' => $secret !== '' ? $token.'.'.$secret : $token];
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.wablas.base_url'), '/').$path;
    }
}
