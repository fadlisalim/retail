<?php

namespace App\Services\Shipping;

use App\Models\CustomerAddress;

/**
 * Tujuan pengiriman lengkap (bukan cuma provinsi/kota) — dipakai driver API
 * kurir yang butuh ID kelurahan tujuan. `addressId` memungkinkan hasil
 * pencarian ID disimpan kembali ke alamat pelanggan lama.
 */
final class ShippingDestination
{
    public function __construct(
        public readonly string $province,
        public readonly ?string $city = null,
        public readonly ?string $district = null,
        public readonly ?string $subdistrict = null,
        public readonly ?string $postalCode = null,
        public readonly ?int $courierDestinationId = null,
        public readonly ?int $addressId = null,
    ) {}

    public static function fromAddress(CustomerAddress $address): self
    {
        return new self(
            province: (string) $address->province,
            city: $address->city,
            district: $address->district,
            subdistrict: $address->subdistrict,
            postalCode: $address->postal_code,
            courierDestinationId: $address->courier_destination_id ? (int) $address->courier_destination_id : null,
            addressId: $address->id,
        );
    }

    /** Kata kunci pencarian tujuan bila ID kelurahan belum tersimpan. */
    public function searchKeyword(): ?string
    {
        $parts = array_filter([$this->district ?: $this->subdistrict, $this->city], fn ($p) => trim((string) $p) !== '');
        $keyword = trim(implode(' ', $parts));

        return mb_strlen($keyword) >= 3 ? $keyword : null;
    }
}
