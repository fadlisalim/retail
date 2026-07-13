<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\SettingService;

/**
 * Manual bank transfer. No webhook — the customer uploads proof and an admin
 * (payment.manage) verifies it, which calls OrderService::markPaid.
 */
class ManualTransferGateway implements PaymentGateway
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function code(): string
    {
        return 'manual_transfer';
    }

    public function label(): string
    {
        return 'Transfer Bank Manual';
    }

    public function isAsync(): bool
    {
        return false;
    }

    public function createCharge(Payment $payment): GatewayResult
    {
        $bank = $this->settings->get('payment.bank_account', 'BCA 123-456-7890 a.n. PT Rekasurya Primadaya');

        return new GatewayResult(
            status: PaymentStatus::AwaitingVerification,
            reference: $bank,
            meta: ['instructions' => 'Transfer sejumlah total pesanan, lalu unggah bukti pembayaran.'],
        );
    }

    public function verifySignature(string $rawBody, string $signature): bool
    {
        return false; // manual transfers never arrive via webhook
    }

    public function parseWebhook(array $payload): GatewayResult
    {
        return new GatewayResult(status: PaymentStatus::Unpaid);
    }
}
