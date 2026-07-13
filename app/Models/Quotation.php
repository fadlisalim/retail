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
        'rfq_number', 'quotation_number', 'public_token', 'user_id', 'status',
        'contact_name', 'contact_email', 'contact_phone', 'company_name', 'npwp',
        'project_name', 'project_location', 'procurement_target', 'needs_installation', 'technical_notes',
        'items_subtotal', 'discount', 'shipping_cost', 'tax_amount', 'grand_total',
        'payment_terms', 'valid_until', 'admin_note', 'converted_order_id', 'handled_by',
    ];

    protected $casts = [
        'needs_installation' => 'boolean',
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
}
