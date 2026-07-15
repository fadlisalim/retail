<?php

namespace App\Enums;

/** Lifecycle of an affiliate account. Stored as string. */
enum AffiliateStatus: string
{
    case Pending = 'pending';     // applied, awaiting data verification
    case Active = 'active';       // verified & earning
    case Rejected = 'rejected';   // application declined
    case Suspended = 'suspended'; // temporarily disabled

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Verifikasi',
            self::Active => 'Aktif',
            self::Rejected => 'Ditolak',
            self::Suspended => 'Ditangguhkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Active => 'green',
            self::Rejected, self::Suspended => 'red',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
