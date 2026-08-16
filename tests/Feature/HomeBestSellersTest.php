<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Beranda: "Produk Terlaris" dari sold_count nyata, fallback ke unggulan. */
class HomeBestSellersTest extends TestCase
{
    use RefreshDatabase;

    public function test_best_sellers_replace_the_featured_section_once_sales_exist(): void
    {
        $this->stockedProduct(5, ['name' => 'Produk Laris Manis', 'sold_count' => 17, 'status' => 'published', 'published_at' => now()]);
        $this->stockedProduct(5, ['name' => 'Produk Unggulan Saja', 'is_featured' => true, 'sold_count' => 0, 'status' => 'published', 'published_at' => now()]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Produk Terlaris')
            ->assertSee('Produk Laris Manis')
            // Kartu produk menampilkan jumlah lakunya.
            ->assertSee('17 terjual')
            ->assertDontSee('Produk Terpopuler');
    }

    public function test_without_any_sales_the_featured_section_still_shows(): void
    {
        $this->stockedProduct(5, ['name' => 'Produk Unggulan Saja', 'is_featured' => true, 'sold_count' => 0, 'status' => 'published', 'published_at' => now()]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Produk Terpopuler')
            ->assertSee('Produk Unggulan Saja');
    }

    public function test_best_sellers_are_ordered_by_units_sold(): void
    {
        $this->stockedProduct(5, ['name' => 'Laris Nomor Dua', 'sold_count' => 3, 'status' => 'published', 'published_at' => now()]);
        $this->stockedProduct(5, ['name' => 'Laris Nomor Satu', 'sold_count' => 42, 'status' => 'published', 'published_at' => now()]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'Laris Nomor Dua'),
            strpos($html, 'Laris Nomor Satu'),
            'Produk dengan penjualan terbanyak harus tampil lebih dulu.',
        );
    }
}
