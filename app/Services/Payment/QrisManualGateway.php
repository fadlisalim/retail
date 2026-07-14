<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\SettingService;

/**
 * Static QRIS: the merchant's own QRIS image (uploaded in Admin → Pengaturan) is
 * shown to the customer, who scans, pays, uploads proof, and an admin verifies —
 * same manual flow as bank transfer, no gateway API required.
 */
class QrisManualGateway implements PaymentGateway
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function code(): string
    {
        return 'qris';
    }

    public function label(): string
    {
        return 'QRIS';
    }

    public function isAsync(): bool
    {
        return false;
    }

    public function createCharge(Payment $payment): GatewayResult
    {
        return new GatewayResult(
            status: PaymentStatus::AwaitingVerification,
            meta: [
                'qris_image' => (string) $this->settings->get('payment.qris_image', ''),
                'instructions' => 'Scan QRIS di atas dengan aplikasi mobile banking / e-wallet Anda, lalu unggah bukti pembayaran.',
            ],
        );
    }

    public function verifySignature(string $rawBody, string $signature): bool
    {
        return false;
    }

    public function parseWebhook(array $payload): GatewayResult
    {
        return new GatewayResult(status: PaymentStatus::Unpaid);
    }
}
