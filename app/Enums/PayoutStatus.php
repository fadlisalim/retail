<?php

namespace App\Enums;

/** Lifecycle of an affiliate payout (withdrawal) request. */
enum PayoutStatus: string
{
    case Requested = 'requested'; // affiliate asked to withdraw
    case Approved = 'approved';   // admin approved, transfer in progress
    case Paid = 'paid';           // transferred & settled
    case Rejected = 'rejected';   // declined

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Diajukan',
            self::Approved => 'Disetujui',
            self::Paid => 'Dibayar',
            self::Rejected => 'Ditolak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'amber',
            self::Approved => 'blue',
            self::Paid => 'green',
            self::Rejected => 'red',
        };
    }

    /** Payouts that still reserve balance (not yet rejected). */
    public function holdsBalance(): bool
    {
        return $this !== self::Rejected;
    }
}
