<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Reference async gateway (virtual account style). Demonstrates the SECURITY model
 * a real integration must follow:
 *   - HMAC-SHA256 signature verification with a secret from .env (never hardcoded)
 *   - a stable event id so the webhook manager can reject replays
 *   - status derived from the payload, but the charged AMOUNT is taken from our
 *     own order record, never from the webhook (prevents amount manipulation).
 */
class DemoGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'va_demo';
    }

    public function label(): string
    {
        return 'Virtual Account (Demo Gateway)';
    }

    public function isAsync(): bool
    {
        return true;
    }

    public function createCharge(Payment $payment): GatewayResult
    {
        $va = '8808'.str_pad((string) $payment->order_id, 10, '0', STR_PAD_LEFT);

        return new GatewayResult(
            status: PaymentStatus::AwaitingVerification,
            externalId: 'DEMO-'.strtoupper(Str::random(10)),
            reference: $va,
            meta: ['va_number' => $va],
        );
    }

    public function verifySignature(string $rawBody, string $signature): bool
    {
        $secret = (string) config('services.demo_gateway.secret', '');
        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(array $payload): GatewayResult
    {
        $status = match ($payload['status'] ?? '') {
            'PAID', 'SETTLED' => PaymentStatus::Paid,
            'EXPIRED' => PaymentStatus::Expired,
            'FAILED' => PaymentStatus::Failed,
            default => PaymentStatus::AwaitingVerification,
        };

        return new GatewayResult(
            status: $status,
            externalId: $payload['external_id'] ?? null,
            eventId: $payload['event_id'] ?? ($payload['external_id'] ?? null),
        );
    }
}
