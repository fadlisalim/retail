<?php

namespace App\Models;

use App\Models\Concerns\RecordsSlugHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, RecordsSlugHistory, SoftDeletes;

    protected $fillable = [
        'parent_id', 'name', 'slug', 'path', 'depth', 'icon', 'image_path', 'banner_path',
        'description', 'is_featured', 'is_active', 'sort_order', 'meta_title', 'meta_description',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function slugHistories(): HasMany
    {
        return $this->hasMany(CategorySlugHistory::class);
    }

    public function attributeGroups(): BelongsToMany
    {
        return $this->belongsToMany(AttributeGroup::class, 'attribute_group_category');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /** Ancestor chain from root to this category (inclusive), for breadcrumbs. */
    public function ancestors(): array
    {
        $chain = [];
        $node = $this;
        while ($node) {
            array_unshift($chain, $node);
            $node = $node->parent;
        }

        return $chain;
    }

    /** IDs of this category and all descendants — used to scope catalog queries. */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }
}
