<?php

namespace App\Models;

use App\Enums\ProductCondition;
use App\Models\Concerns\RecordsSlugHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, RecordsSlugHistory, SoftDeletes;

    protected $fillable = [
        'sku', 'name', 'slug', 'category_id', 'brand_id', 'model', 'product_type', 'condition',
        'short_description', 'description', 'specifications',
        'price', 'sale_price', 'cost_price', 'affiliate_rate', 'price_status', 'price_includes_tax', 'is_taxable',
        'stock', 'min_stock', 'unit',
        'weight_grams', 'length_cm', 'width_cm', 'height_cm', 'package_count',
        'can_combine_package', 'requires_freight', 'pickup_only',
        'warranty', 'estimated_processing', 'main_image_path',
        'status', 'is_featured', 'is_new', 'is_promo', 'is_clearance', 'badge_text',
        'is_purchasable', 'requires_quotation', 'min_purchase', 'max_purchase',
        'meta_title', 'meta_description', 'keywords', 'canonical_url', 'published_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'affiliate_rate' => 'decimal:2',
        'price_includes_tax' => 'boolean',
        'is_taxable' => 'boolean',
        'can_combine_package' => 'boolean',
        'requires_freight' => 'boolean',
        'pickup_only' => 'boolean',
        'is_featured' => 'boolean',
        'is_new' => 'boolean',
        'is_promo' => 'boolean',
        'is_clearance' => 'boolean',
        'is_purchasable' => 'boolean',
        'requires_quotation' => 'boolean',
        'rating_avg' => 'decimal:2',
        'published_at' => 'datetime',
    ];

    // cost_price is admin-only; never expose by default when serialising.
    protected $hidden = ['cost_price'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /* ------------------------------------------------------------------ */
    /* Relationships                                                       */
    /* ------------------------------------------------------------------ */

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProductDocument::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ProductVideo::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function conditionDetail(): HasOne
    {
        return $this->hasOne(ProductConditionDetail::class);
    }

    public function bundleItems(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_product_id')->orderBy('sort_order');
    }

    public function slugHistories(): HasMany
    {
        return $this->hasMany(ProductSlugHistory::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function visibleReviews(): HasMany
    {
        return $this->reviews()->where('is_visible', true);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    /* ------------------------------------------------------------------ */
    /* Scopes                                                              */
    /* ------------------------------------------------------------------ */

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopePurchasable(Builder $query): Builder
    {
        return $query->where('is_purchasable', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    /* ------------------------------------------------------------------ */
    /* Pricing (server-authoritative helpers)                              */
    /* ------------------------------------------------------------------ */

    public function conditionEnum(): ProductCondition
    {
        return ProductCondition::tryFrom($this->condition) ?? ProductCondition::New;
    }

    public function isOnSale(): bool
    {
        return $this->sale_price !== null && (float) $this->sale_price > 0
            && (float) $this->sale_price < (float) $this->price;
    }

    /** The price a customer actually pays (before tax adjustments). */
    public function effectivePrice(): float
    {
        return (float) ($this->isOnSale() ? $this->sale_price : $this->price);
    }

    public function discountPercent(): int
    {
        if (! $this->isOnSale() || (float) $this->price <= 0) {
            return 0;
        }

        return (int) round((1 - ((float) $this->sale_price / (float) $this->price)) * 100);
    }

    public function inStock(): bool
    {
        return $this->stock > 0;
    }

    public function isLowStock(): bool
    {
        return $this->stock > 0 && $this->stock <= max(1, $this->min_stock);
    }

    public function requiresConditionAck(): bool
    {
        return $this->conditionEnum()->requiresAcknowledgement();
    }

    /** Badge labels shown on cards/detail (section 10). */
    public function badges(bool $includeCondition = true, bool $includeCustom = true): array
    {
        // Ordered by display priority so cards (which show the first ~2) surface the
        // most important first: custom tag, Clearance, Promo, Stok Terbatas, Baru.
        // The card renders the custom tag as a separate inline chip (near the price),
        // so it passes $includeCustom = false to keep the image overlay uncluttered.
        $badges = [];
        if ($includeCustom && filled($this->badge_text)) $badges[] = $this->badge_text;
        if ($this->is_clearance) $badges[] = 'Clearance';
        if ($this->isOnSale()) $badges[] = 'Promo';
        if ($this->isLowStock()) $badges[] = 'Stok Terbatas';
        if ($this->is_new) $badges[] = 'Baru';
        if ($includeCondition && $this->condition !== ProductCondition::New->value) {
            $badges[] = $this->conditionEnum()->label();
        }
        if ($this->conditionDetail?->is_negotiable) $badges[] = 'Harga Nego';
        if ($this->pickup_only) $badges[] = 'Ambil di Lokasi';
        if ($this->requires_quotation) $badges[] = 'Minta Penawaran';

        return array_unique($badges);
    }

    public function primaryImageUrl(): string
    {
        if ($this->main_image_path) {
            return asset('storage/'.$this->main_image_path);
        }

        // Deterministic inline SVG placeholder so the catalog renders without assets.
        return $this->placeholderImage();
    }

    public function placeholderImage(): string
    {
        $label = rawurlencode(mb_strimwidth($this->brand?->name ?? 'Rekasurya', 0, 18));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600"><rect width="100%" height="100%" fill="#e6f4f1"/><text x="50%" y="50%" font-family="sans-serif" font-size="34" fill="#0f766e" text-anchor="middle" dominant-baseline="middle">'.htmlspecialchars($this->brand?->name ?? brand()).'</text></svg>';

        return 'data:image/svg+xml;charset=UTF-8,'.rawurlencode($svg);
    }
}
