<?php

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Halaman afiliator "Produk & Komisi": urutan fee, link referral, varian. */
class AffiliateProductsPageTest extends TestCase
{
    use RefreshDatabase;

    private function activeAffiliate(): Affiliate
    {
        $user = User::factory()->create(['is_staff' => false, 'is_active' => true]);

        return Affiliate::create([
            'user_id' => $user->id, 'code' => 'KODE99', 'status' => AffiliateStatus::Active->value,
            'full_name' => 'Afiliator Uji', 'id_number' => '123', 'phone' => '0812', 'address' => 'Jkt', 'channel' => 'IG',
        ]);
    }

    public function test_products_are_ordered_by_fee_and_carry_the_personal_link(): void
    {
        $affiliate = $this->activeAffiliate();
        $this->stockedProduct(5, ['name' => 'Produk Fee Kecil', 'affiliate_rate' => 1, 'price' => 1_000_000, 'status' => 'published', 'published_at' => now()]);
        $top = $this->stockedProduct(5, ['name' => 'Produk Fee Jumbo', 'affiliate_rate' => 8, 'price' => 2_000_000, 'status' => 'published', 'published_at' => now()]);

        $response = $this->actingAs($affiliate->user)->get(route('account.affiliate.products'))->assertOk();
        $html = $response->getContent();

        // Fee terbesar tampil lebih dulu, lengkap dengan link ?ref=KODE.
        $this->assertLessThan(strpos($html, 'Produk Fee Kecil'), strpos($html, 'Produk Fee Jumbo'));
        $response->assertSee('8%')->assertSee($top->slug.'?ref=KODE99', false);
        // Perkiraan komisi per unit: 8% × 2 jt.
        $response->assertSee('160.000');
    }

    public function test_a_null_rate_sorts_by_the_store_default(): void
    {
        $this->activeAffiliate();
        // Default 2,5% > 1% — produk tanpa rate harus di atas produk 1%.
        $this->stockedProduct(5, ['name' => 'Produk Rate Default', 'affiliate_rate' => null, 'price' => 500_000, 'status' => 'published', 'published_at' => now()]);
        $this->stockedProduct(5, ['name' => 'Produk Satu Persen', 'affiliate_rate' => 1, 'price' => 500_000, 'status' => 'published', 'published_at' => now()]);

        $html = $this->actingAs(Affiliate::first()->user)
            ->get(route('account.affiliate.products'))->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Produk Satu Persen'), strpos($html, 'Produk Rate Default'));
    }

    public function test_variants_are_listed_on_variable_products(): void
    {
        $affiliate = $this->activeAffiliate();
        $product = $this->stockedProduct(0, ['name' => 'Power Station Varian', 'product_type' => 'variable', 'affiliate_rate' => 5, 'price' => 7_000_000, 'status' => 'published', 'published_at' => now()]);
        $product->variants()->create(['sku' => 'PSV-1', 'name' => 'Varian 1 kWh', 'option_values' => ['Unit' => '1 kWh'], 'price' => 7_000_000, 'is_active' => true, 'sort_order' => 0]);
        $product->variants()->create(['sku' => 'PSV-2', 'name' => 'Varian 2 kWh', 'option_values' => ['Unit' => '2 kWh'], 'price' => 11_000_000, 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($affiliate->user)
            ->get(route('account.affiliate.products'))
            ->assertOk()
            ->assertSee('Varian 1 kWh')
            ->assertSee('Varian 2 kWh');
    }

    public function test_quotation_only_products_are_excluded(): void
    {
        $affiliate = $this->activeAffiliate();
        $this->stockedProduct(0, ['name' => 'Inverter Proyek 40kW', 'requires_quotation' => true, 'affiliate_rate' => 9, 'status' => 'published', 'published_at' => now()]);

        $this->actingAs($affiliate->user)
            ->get(route('account.affiliate.products'))
            ->assertOk()
            ->assertDontSee('Inverter Proyek 40kW');
    }

    /** Tabel komisi juga tampil di landing publik /afiliasi. */
    public function test_the_public_landing_shows_the_commission_table(): void
    {
        $this->stockedProduct(5, ['name' => 'Produk Fee Jumbo', 'affiliate_rate' => 8, 'price' => 2_000_000, 'status' => 'published', 'published_at' => now()]);

        // Tamu: tabel tampil tanpa tombol salin link.
        $this->get(route('affiliate.landing'))
            ->assertOk()
            ->assertSee('Tabel Komisi per Produk')
            ->assertSee('Produk Fee Jumbo')
            ->assertSee('160.000')
            ->assertDontSee('Salin Link');

        // Afiliator aktif: tombol salin link pribadinya ikut tampil.
        $affiliate = $this->activeAffiliate();
        $this->actingAs($affiliate->user)
            ->get(route('affiliate.landing'))
            ->assertOk()
            ->assertSee('Salin Link')
            ->assertSee('?ref=KODE99', false);
    }

    public function test_non_active_affiliates_are_sent_back_to_the_dashboard(): void
    {
        $user = User::factory()->create(['is_staff' => false, 'is_active' => true]);
        Affiliate::create([
            'user_id' => $user->id, 'code' => 'PENDNG', 'status' => AffiliateStatus::Pending->value,
            'full_name' => 'Calon', 'id_number' => '1', 'phone' => '08', 'address' => 'Jkt', 'channel' => 'IG',
        ]);

        $this->actingAs($user)
            ->get(route('account.affiliate.products'))
            ->assertRedirect(route('account.affiliate.dashboard'));
    }
}
