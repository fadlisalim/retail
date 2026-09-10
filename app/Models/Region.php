<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Wilayah Indonesia (province → city → district → subdistrict) yang disinkron
 * dari RajaOngkir/Komerce; `code` = ID wilayah di RajaOngkir, dipakai sebagai
 * tujuan perhitungan ongkir kurir reguler.
 */
class Region extends Model
{
    public const TYPES = ['province', 'city', 'district', 'subdistrict'];

    protected $fillable = ['parent_id', 'code', 'name', 'type', 'postal_code', 'children_synced_at'];

    protected $casts = ['children_synced_at' => 'datetime'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Region::class, 'parent_id');
    }

    /** Tipe anak dari tipe ini (province → city, …); null untuk kelurahan. */
    public function childType(): ?string
    {
        $i = array_search($this->type, self::TYPES, true);

        return $i === false ? null : (self::TYPES[$i + 1] ?? null);
    }

    /** Rantai induk sampai provinsi: [province, city, district, subdistrict]. */
    public function chain(): array
    {
        $chain = [];
        $node = $this;
        while ($node) {
            array_unshift($chain, $node);
            $node = $node->parent;
        }

        return $chain;
    }

    /** "Sub, Kecamatan, Kota, Provinsi, kode pos" untuk label tujuan kurir. */
    public function courierLabel(): string
    {
        $names = array_reverse(array_map(fn (Region $r) => $r->name, $this->chain()));

        return implode(', ', array_filter([...$names, $this->postal_code]));
    }
}
