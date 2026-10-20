<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'rfq_number', 'quotation_number', 'public_token', 'user_id', 'site_visit_id', 'status',
        'contact_name', 'contact_email', 'contact_phone', 'company_name', 'npwp',
        'requester_role', 'decision_role',
        'project_name', 'project_type', 'project_status', 'funding_source', 'budget_range',
        'project_location', 'unit_scale', 'procurement_target',
        'needs_installation', 'needs_survey', 'needs_tender_docs', 'technical_notes', 'requirements',
        'items_subtotal', 'discount', 'shipping_cost', 'tax_amount', 'grand_total',
        'payment_terms', 'valid_until', 'admin_note', 'converted_order_id', 'handled_by',
        'cost_total', 'rab_approved_by', 'rab_approved_name', 'rab_approved_at', 'rab_signature', 'rab_note', 'rab_token',
    ];

    // Data RAB (modal, persetujuan, link tim) hanya untuk internal.
    protected $hidden = ['cost_total', 'rab_approved_by', 'rab_approved_name', 'rab_approved_at', 'rab_signature', 'rab_note', 'rab_token'];

    protected $casts = [
        'cost_total' => 'decimal:2',
        'rab_approved_at' => 'datetime',
        'needs_installation' => 'boolean',
        'needs_survey' => 'boolean',
        'needs_tender_docs' => 'boolean',
        'requirements' => 'array',
        'procurement_target' => 'date',
        'valid_until' => 'date',
        'items_subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'status' => QuotationStatus::class,
    ];

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function siteVisit(): BelongsTo
    {
        return $this->belongsTo(SiteVisit::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(QuotationRevision::class)->orderByDesc('version');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(QuotationAttachment::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function rabApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rab_approved_by');
    }

    public function rabReviews(): HasMany
    {
        return $this->hasMany(QuotationRabReview::class)->latest('id');
    }

    /** Nama penyetuju RAB (user admin atau nama yang ditulis tim di link review). */
    public function rabApproverName(): ?string
    {
        return $this->rab_approved_name ?: $this->rabApprover?->name;
    }

    /**
     * Ringkasan RAB untuk review internal. Pendapatan = subtotal item − diskon tambahan
     * (sebelum PPN; ongkir/instalasi di header tidak dihitung). Modal = Σ modal baris.
     *
     * @return array{revenue: float, cost: float, margin: float, percent: ?float, missing: int, lines: int}
     */
    public function rab(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $revenue = round((float) $this->items_subtotal - (float) $this->discount, 2);
        $cost = round((float) $items->sum(fn (QuotationItem $i) => $i->lineCost() ?? 0), 2);
        $margin = round($revenue - $cost, 2);

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'margin' => $margin,
            'percent' => $revenue > 0 ? round($margin / $revenue * 100, 1) : null,
            'missing' => $items->filter(fn (QuotationItem $i) => $i->unit_cost === null)->count(),
            'lines' => $items->count(),
        ];
    }

    public function rabApproved(): bool
    {
        return $this->rab_approved_at !== null && $this->rab_signature !== null;
    }
}
