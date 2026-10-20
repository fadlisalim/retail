<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu review RAB dari link tim (tanpa login): setuju / minta revisi + nama peninjau. */
class QuotationRabReview extends Model
{
    public const APPROVED = 'approved';

    public const REVISION = 'revision';

    public const DIVISIONS = [
        'engineering' => 'Engineering',
        'purchasing' => 'Purchasing',
        'finance' => 'Keuangan',
        'management' => 'Manajemen / GM',
        'sales' => 'Sales',
        'other' => 'Lainnya',
    ];

    protected $fillable = [
        'quotation_id', 'reviewer_name', 'division', 'decision', 'note', 'signature', 'margin_percent', 'ip', 'user_agent',
    ];

    protected $casts = [
        'margin_percent' => 'decimal:1',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function divisionLabel(): string
    {
        return self::DIVISIONS[$this->division] ?? 'Lainnya';
    }

    public function isApproval(): bool
    {
        return $this->decision === self::APPROVED;
    }
}
