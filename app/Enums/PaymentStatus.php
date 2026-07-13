<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case AwaitingVerification = 'awaiting_verification';
    case DownPaymentPaid = 'down_payment_paid';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum Dibayar',
            self::AwaitingVerification => 'Menunggu Verifikasi',
            self::DownPaymentPaid => 'DP Dibayar',
            self::Paid => 'Lunas',
            self::Failed => 'Gagal',
            self::Expired => 'Kedaluwarsa',
            self::PartiallyRefunded => 'Dikembalikan Sebagian',
            self::Refunded => 'Dikembalikan Penuh',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'green',
            self::DownPaymentPaid, self::AwaitingVerification => 'amber',
            self::Failed, self::Expired => 'red',
            self::PartiallyRefunded, self::Refunded => 'purple',
            self::Unpaid => 'gray',
        };
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::DownPaymentPaid], true);
    }

    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
