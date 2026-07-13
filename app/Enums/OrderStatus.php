<?php

namespace App\Enums;

/**
 * Order lifecycle. Stored as string so future statuses can be added without a
 * data migration; this enum is the canonical list plus Indonesian labels.
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case AwaitingShippingConfirmation = 'awaiting_shipping_confirmation';
    case AwaitingPayment = 'awaiting_payment';
    case PaymentVerified = 'payment_verified';
    case Processing = 'processing';
    case Packing = 'packing';
    case ReadyForPickup = 'ready_for_pickup';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case ReturnRequested = 'return_requested';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::AwaitingShippingConfirmation => 'Menunggu Konfirmasi Ongkir',
            self::AwaitingPayment => 'Menunggu Pembayaran',
            self::PaymentVerified => 'Pembayaran Diverifikasi',
            self::Processing => 'Sedang Diproses',
            self::Packing => 'Sedang Dikemas',
            self::ReadyForPickup => 'Siap Diambil',
            self::Shipped => 'Dikirim',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
            self::ReturnRequested => 'Pengembalian Diajukan',
            self::Returned => 'Dikembalikan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::AwaitingShippingConfirmation, self::AwaitingPayment => 'amber',
            self::PaymentVerified, self::Processing, self::Packing, self::ReadyForPickup, self::Shipped => 'blue',
            self::Completed => 'green',
            self::Cancelled, self::ReturnRequested, self::Returned => 'red',
        };
    }

    /** Statuses that still count as "open" for the customer. */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled, self::Returned], true);
    }

    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
