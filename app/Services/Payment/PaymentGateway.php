<?php

namespace App\Services\Payment;

use App\Models\Payment;

/**
 * Contract every payment method implements. Adding a real gateway (Midtrans,
 * Xendit, an aggregator...) means writing one class and registering it — checkout,
 * orders and the webhook endpoint stay unchanged.
 */
interface PaymentGateway
{
    public function code(): string;

    public function label(): string;

    /** Whether this method settles via an asynchronous webhook. */
    public function isAsync(): bool;

    /** Initialise a charge (e.g. request a VA number). Returns normalised data. */
    public function createCharge(Payment $payment): GatewayResult;

    /** Verify the raw webhook body against the provided signature. */
    public function verifySignature(string $rawBody, string $signature): bool;

    /** Parse a verified webhook payload into a normalised result. */
    public function parseWebhook(array $payload): GatewayResult;
}
