<?php

namespace App\Enums;

/**
 * Reason attached to every stock ledger entry. Stock is never stored as a bare
 * final number; each change is an immutable movement so the balance is auditable.
 */
enum StockMovementType: string
{
    case Sale = 'sale';
    case Cancellation = 'cancellation';
    case Return = 'return';
    case Purchase = 'purchase';
    case Adjustment = 'adjustment';
    case Transfer = 'transfer';
    case Damaged = 'damaged';
    case ReservationHold = 'reservation_hold';
    case ReservationRelease = 'reservation_release';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Penjualan',
            self::Cancellation => 'Pembatalan',
            self::Return => 'Retur',
            self::Purchase => 'Pembelian',
            self::Adjustment => 'Penyesuaian',
            self::Transfer => 'Transfer Gudang',
            self::Damaged => 'Barang Rusak',
            self::ReservationHold => 'Reservasi',
            self::ReservationRelease => 'Pelepasan Reservasi',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
