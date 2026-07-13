<?php

namespace App\Enums;

enum QuotationStatus: string
{
    case New = 'new';
    case UnderReview = 'under_review';
    case AwaitingData = 'awaiting_data';
    case QuoteSent = 'quote_sent';
    case Revised = 'revised';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::UnderReview => 'Sedang Ditinjau',
            self::AwaitingData => 'Menunggu Data',
            self::QuoteSent => 'Penawaran Dikirim',
            self::Revised => 'Direvisi',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Expired => 'Kedaluwarsa',
            self::Converted => 'Menjadi Pesanan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'blue',
            self::UnderReview, self::AwaitingData, self::Revised => 'amber',
            self::QuoteSent => 'teal',
            self::Approved, self::Converted => 'green',
            self::Rejected, self::Expired => 'red',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
