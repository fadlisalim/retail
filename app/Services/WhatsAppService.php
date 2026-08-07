<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends WhatsApp messages through the Wablas gateway. Credentials come only from
 * config (env) — never hardcoded. Failures are logged and swallowed so a WA
 * outage never breaks the request that triggered the notification.
 */
class WhatsAppService
{
    /** Diagnostics from the last send(): ['ok'=>bool,'status'=>?int,'body'=>string]. */
    public ?array $lastResult = null;

    public function isEnabled(): bool
    {
        return (bool) config('services.wablas.enabled') && filled(config('services.wablas.token'));
    }

    /**
     * Send a message to a single number via Wablas API v2. Returns true on a
     * successful send. Authorization header carries the token (or token.secret).
     */
    public function send(?string $phone, string $message): bool
    {
        $this->lastResult = null;

        if (! $this->isEnabled()) {
            $this->lastResult = ['ok' => false, 'status' => null, 'body' => 'Wablas belum aktif (env).'];

            return false;
        }

        $phone = $this->normalize($phone);
        if (! $phone || trim($message) === '') {
            $this->lastResult = ['ok' => false, 'status' => null, 'body' => 'Nomor atau pesan kosong/invalid.'];

            return false;
        }

        try {
            // Wablas API v2: JSON body with a `data` array (matches the proven
            // working config). isGroup "false" for a personal number.
            $response = Http::withHeaders([
                'Authorization' => (string) config('services.wablas.token'),
            ])
                ->asJson()
                ->timeout(15)
                ->post($this->endpoint('/api/v2/send-message'), [
                    'data' => [
                        ['phone' => $phone, 'isGroup' => 'false', 'message' => $message],
                    ],
                ]);

            $this->lastResult = ['ok' => false, 'status' => $response->status(), 'body' => $response->body()];

            // Wablas returns { "status": true|false, ... }. Success = 2xx + status true.
            $status = $response->json('status');
            if ($response->successful() && ($status === true || $status === 'true')) {
                $this->lastResult['ok'] = true;

                return true;
            }

            Log::warning('Wablas send failed', ['phone' => $phone, 'status' => $response->status(), 'body' => $response->body()]);
        } catch (\Throwable $e) {
            $this->lastResult = ['ok' => false, 'status' => null, 'body' => $e->getMessage()];
            Log::warning('Wablas send error: '.$e->getMessage(), ['phone' => $phone]);
        }

        return false;
    }

    /**
     * Send an image/document/video by URL. Wablas fetches the file itself, so
     * the URL must be publicly reachable (we upload to the public disk first).
     * Returns true when the gateway accepted it.
     */
    public function sendMedia(?string $phone, string $url, string $type = 'image', string $caption = ''): bool
    {
        $this->lastResult = null;

        if (! $this->isEnabled()) {
            $this->lastResult = ['ok' => false, 'status' => null, 'body' => 'Wablas belum aktif (env).'];

            return false;
        }

        $phone = $this->normalize($phone);
        if (! $phone || ! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->lastResult = ['ok' => false, 'status' => null, 'body' => 'Nomor atau URL media tidak valid.'];

            return false;
        }

        // Endpoint and payload key differ per media type.
        [$path, $key] = match ($type) {
            'video' => ['/api/v2/send-video', 'video'],
            'audio' => ['/api/v2/send-audio', 'audio'],
            'document' => ['/api/v2/send-document', 'document'],
            default => ['/api/v2/send-image', 'image'],
        };

        try {
            $row = ['phone' => $phone, $key => $url, 'isGroup' => 'false'];
            if ($caption !== '') {
                // Documents carry the filename instead of a caption.
                $row[$type === 'document' ? 'filename' : 'caption'] = $caption;
            }

            $response = Http::withHeaders(['Authorization' => (string) config('services.wablas.token')])
                ->asJson()
                ->timeout(30)
                ->post($this->endpoint($path), ['data' => [$row]]);

            $this->lastResult = ['ok' => false, 'status' => $response->status(), 'body' => $response->body()];

            $status = $response->json('status');
            if ($response->successful() && ($status === true || $status === 'true')) {
                $this->lastResult['ok'] = true;

                return true;
            }

            Log::warning('Wablas media send failed', ['phone' => $phone, 'type' => $type, 'body' => $response->body()]);
        } catch (\Throwable $e) {
            $this->lastResult = ['ok' => false, 'status' => null, 'body' => $e->getMessage()];
            Log::warning('Wablas media send error: '.$e->getMessage(), ['phone' => $phone]);
        }

        return false;
    }

    /**
     * Normalise to international format (digits only, with country code). A leading
     * 0 is treated as a legacy Indonesian local number (→ 62…); any other number is
     * assumed to already carry its country code (from the phone picker) and is kept
     * as-is, so non-Indonesian numbers are never corrupted.
     */
    public function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if ($digits === '' || strlen($digits) < 8) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.ltrim(substr($digits, 1), '0');
        }

        return $digits;
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.wablas.base_url'), '/').$path;
    }
}
