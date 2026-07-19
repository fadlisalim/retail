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
     * Send a message to a single number. Returns true on a successful send.
     * The token may be "token" (v1) or "token.secret" (v2); Wablas accepts the
     * combined value on /api/send-message, sent as the Authorization header.
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
            $response = Http::withHeaders([
                'Authorization' => (string) config('services.wablas.token'),
            ])
                ->asForm()
                ->timeout(15)
                ->post($this->endpoint('/api/send-message'), [
                    'phone' => $phone,
                    'message' => $message,
                ]);

            $this->lastResult = ['ok' => false, 'status' => $response->status(), 'body' => $response->body()];

            // Wablas returns { "status": true|false, ... }. Treat an explicit
            // false as failure; otherwise a 2xx is success.
            if ($response->successful() && $response->json('status') !== false) {
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
