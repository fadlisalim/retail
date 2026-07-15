<?php

namespace App\Enums;

/** Lifecycle of a single affiliate commission line. */
enum CommissionStatus: string
{
    case Pending = 'pending';     // order paid, held until the order completes
    case Approved = 'approved';   // order completed — payable
    case Paid = 'paid';           // included in a settled payout
    case Cancelled = 'cancelled'; // order cancelled/returned — voided

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ditahan',
            self::Approved => 'Disetujui',
            self::Paid => 'Dibayar',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'blue',
            self::Paid => 'green',
            self::Cancelled => 'red',
        };
    }
}
