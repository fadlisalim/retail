<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;

/** Normalised result returned by every payment gateway adapter. */
final class GatewayResult
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $externalId = null,
        public readonly ?string $eventId = null,
        public readonly ?string $reference = null,   // VA number / QR payload / instructions
        public readonly array $meta = [],
    ) {
    }
}
