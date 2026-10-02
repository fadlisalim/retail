<?php

namespace Tests\Feature;

use App\Enums\CommissionStatus;
use App\Enums\PaymentStatus;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\MonthlyRevenueReport;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Laporan pendapatan bulanan: pesanan lunas dibukukan di bulan pembayaran,
 * angka per bulan (pendapatan, penjualan, ongkir, PPN, HPP, laba, komisi,
 * kanal), rincian bulan, CSV, dan hak akses keuangan.
 */
class MonthlyRevenueReportTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', $role)->first());

        return $user;
    }

    private function order(array $attrs, array $items = []): Order
    {
        // forceFill: created_at / cancelled_at bukan fillable, tapi test perlu tanggal tertentu.
        $order = (new Order)->forceFill(array_merge([
            'order_number' => 'ORD-'.Str::upper(Str::random(6)), 'public_token' => Str::uuid(),
            'customer_name' => 'Budi', 'customer_email' => 'budi@test.id', 'customer_phone' => '0812',
            'status' => 'processing', 'payment_status' => PaymentStatus::Paid->value,
            'items_subtotal' => 0, 'tax_amount' => 0, 'grand_total' => 0,
            'finance_verified_at' => ($attrs['payment_status'] ?? PaymentStatus::Paid->value) === PaymentStatus::Paid->value ? now() : null, // lunas terverifikasi Keuangan
        ], $attrs));
        $order->save();
        foreach ($items as $i) {
            $order->items()->create(array_merge(['sku' => 'SKU', 'name' => 'Item', 'unit_price' => 0, 'quantity' => 1, 'line_total' => 0], $i));
        }

        return $order;
    }

    public function test_paid_orders_are_grouped_by_payment_month_with_cost_and_commission(): void
    {
        $this->defaultWarehouse();
        $panel = Product::factory()->create(['name' => 'Panel 580', 'sku' => 'PNL-580', 'price' => 1_900_000, 'cost_price' => 1_500_000]);
        $noCost = Product::factory()->create(['name' => 'Tanpa Modal', 'sku' => 'NOCOST', 'price' => 100_000, 'cost_price' => null]);

        // September 2026: 2 pesanan lunas (satu dibayar Oktober → masuk Oktober).
        $sep = $this->order([
            'paid_at' => '2026-09-05 10:00:00', 'created_at' => '2026-09-04 09:00:00', 'channel' => 'website',
            'items_subtotal' => 3_800_000, 'coupon_discount' => 100_000, 'shipping_cost' => 150_000, 'packing_fee' => 50_000,
            'tax_amount' => 418_000, 'grand_total' => 4_318_000,
        ], [['product_id' => $panel->id, 'sku' => 'PNL-580', 'name' => 'Panel 580', 'unit_price' => 1_900_000, 'quantity' => 2, 'line_total' => 3_800_000]]);
        $this->order([
            'paid_at' => '2026-09-20 10:00:00', 'channel' => 'tokopedia',
            'items_subtotal' => 100_000, 'grand_total' => 100_000,
        ], [['product_id' => $noCost->id, 'sku' => 'NOCOST', 'name' => 'Tanpa Modal', 'unit_price' => 100_000, 'quantity' => 1, 'line_total' => 100_000]]);
        $okt = $this->order([
            'paid_at' => '2026-10-01 08:00:00', 'created_at' => '2026-09-29 08:00:00',
            'items_subtotal' => 1_900_000, 'grand_total' => 1_900_000,
        ], [['product_id' => $panel->id, 'sku' => 'PNL-580', 'name' => 'Panel 580', 'unit_price' => 1_900_000, 'quantity' => 1, 'line_total' => 1_900_000]]);
        // Belum bayar & dibatalkan tidak masuk pendapatan, hanya dihitung.
        $this->order(['payment_status' => PaymentStatus::Unpaid->value, 'status' => 'awaiting_payment', 'created_at' => '2026-09-10 10:00:00', 'grand_total' => 999]);
        $this->order(['payment_status' => PaymentStatus::Unpaid->value, 'status' => 'cancelled', 'cancelled_at' => '2026-09-11 10:00:00', 'created_at' => '2026-09-10 10:00:00', 'grand_total' => 999]);
        // Pesanan lama tanpa paid_at → bulan created_at.
        $this->order(['paid_at' => null, 'created_at' => '2026-08-15 10:00:00', 'items_subtotal' => 500_000, 'grand_total' => 500_000]);

        // Komisi afiliasi: satu aktif, satu dibatalkan.
        $affUser = User::factory()->create();
        $aff = Affiliate::create(['user_id' => $affUser->id, 'code' => 'AFF1', 'status' => 'active', 'full_name' => 'A', 'bank_name' => 'BCA', 'bank_account_number' => '1', 'bank_account_holder' => 'A']);
        AffiliateCommission::create(['affiliate_id' => $aff->id, 'order_id' => $sep->id, 'order_item_id' => $sep->items->first()->id, 'product_id' => $panel->id, 'base_amount' => 3_800_000, 'rate' => 5, 'amount' => 190_000, 'status' => CommissionStatus::Pending]);
        AffiliateCommission::create(['affiliate_id' => $aff->id, 'order_id' => $okt->id, 'order_item_id' => $okt->items->first()->id, 'product_id' => $panel->id, 'base_amount' => 1, 'rate' => 5, 'amount' => 50_000, 'status' => CommissionStatus::Cancelled]);

        $data = app(MonthlyRevenueReport::class)->year(2026);
        $sepRow = $data['months'][9];
        $this->assertSame(2, $sepRow['pesanan']);
        $this->assertEquals(4_418_000, $sepRow['pendapatan']);
        $this->assertEquals(3_800_000, $sepRow['penjualan']);  // 3,8jt − 100rb voucher + 100rb
        $this->assertEquals(200_000, $sepRow['ongkir_biaya']);
        $this->assertEquals(418_000, $sepRow['ppn']);
        $this->assertEquals(3_000_000, $sepRow['hpp']);        // 2 × 1,5jt; item tanpa modal = 0
        $this->assertEquals(800_000, $sepRow['laba_kotor']);
        $this->assertSame(1, $sepRow['item_tanpa_modal']);
        $this->assertEquals(190_000, $sepRow['komisi']);      // yang dibatalkan tidak ikut
        $this->assertSame(1, $sepRow['belum_bayar']);
        $this->assertSame(1, $sepRow['dibatalkan']);
        $this->assertSame(['website' => ['pesanan' => 1, 'pendapatan' => 4_318_000.0], 'tokopedia' => ['pesanan' => 1, 'pendapatan' => 100_000.0]], $sepRow['channel']);

        $this->assertSame(1, $data['months'][10]['pesanan']);
        $this->assertEquals(1_900_000, $data['months'][10]['pendapatan']);
        $this->assertEquals(0, $data['months'][10]['komisi']); // komisi dibatalkan tidak ikut
        $this->assertEquals(500_000, $data['months'][8]['pendapatan']);
        $this->assertSame(4, $data['total']['pesanan']);
        $this->assertEquals(6_818_000, $data['total']['pendapatan']);
        $this->assertEquals(6_818_000 / 4, $data['total']['rata_rata']);

        $detail = app(MonthlyRevenueReport::class)->month(2026, 9);
        $this->assertCount(2, $detail['orders']);
        $this->assertSame('Panel 580', $detail['products'][0]['name']);
        $this->assertSame(2, $detail['products'][0]['qty']);
    }

    public function test_finance_sees_the_page_and_csv_with_cost_columns(): void
    {
        $this->defaultWarehouse();
        $this->order(['paid_at' => '2026-03-03 10:00:00', 'items_subtotal' => 2_000_000, 'grand_total' => 2_000_000, 'customer_name' => 'Pak Sutrisno']);

        $finance = $this->staff('admin-keuangan');
        $this->actingAs($finance)->get(route('admin.reports.monthly', ['tahun' => 2026]))
            ->assertOk()
            ->assertSee('Laporan Pendapatan')
            ->assertSee('Maret 2026')
            ->assertSee('Rp 2.000.000')
            ->assertSee('Unduh CSV');

        $this->actingAs($finance)->get(route('admin.reports.monthly', ['tahun' => 2026, 'bulan' => 3]))
            ->assertOk()
            ->assertSee('Produk Terlaris')
            ->assertSee('Pak Sutrisno');

        $csv = $this->actingAs($finance)->get(route('admin.reports.monthly', ['tahun' => 2026, 'export' => 'csv']));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Bulan;"Pesanan Lunas";Pendapatan', $body);
        $this->assertStringContainsString('"Estimasi HPP";"Laba Kotor"', $body);
        $this->assertStringContainsString('"Maret 2026";1;2000000', $body);
        $this->assertStringContainsString('"TOTAL 2026";1;2000000', $body);

        $this->actingAs($finance)->get(route('admin.dashboard'))->assertSee('Laporan Pendapatan');
    }

    /** Semua staf boleh memantau rekap pendapatan; HPP/laba kotor hanya untuk Keuangan & yang boleh lihat modal. */
    public function test_other_staff_see_the_recap_without_cost_columns(): void
    {
        $this->defaultWarehouse();
        $this->order(['paid_at' => '2026-03-03 10:00:00', 'items_subtotal' => 2_000_000, 'grand_total' => 2_000_000]);

        foreach (['admin-sales', 'admin-gudang', 'customer-service', 'admin-konten'] as $role) {
            $staff = $this->staff($role);
            $this->actingAs($staff)->get(route('admin.dashboard'))->assertSee('Laporan Pendapatan');
            $this->actingAs($staff)->get(route('admin.reports.monthly', ['tahun' => 2026]))
                ->assertOk()->assertSee('Maret 2026')->assertSee('Rp 2.000.000')
                ->assertDontSee('Laba Kotor')->assertDontSee('HPP');

            $body = $this->actingAs($staff)->get(route('admin.reports.monthly', ['tahun' => 2026, 'export' => 'csv']))->assertOk()->streamedContent();
            $this->assertStringNotContainsString('HPP', $body);
            $this->assertStringNotContainsString('Laba Kotor', $body);
            $this->assertStringContainsString('"Maret 2026";1;2000000;2000000;0;0;0;', $body); // kolom HPP & laba dilewati
        }

        // Admin katalog boleh lihat modal → kolom HPP tampil.
        $this->actingAs($this->staff('admin-katalog'))->get(route('admin.reports.monthly', ['tahun' => 2026]))->assertOk()->assertSee('Laba Kotor');
    }

    public function test_year_selector_falls_back_to_a_year_that_exists(): void
    {
        $this->defaultWarehouse();
        $this->order(['paid_at' => '2025-12-20 10:00:00', 'grand_total' => 10_000, 'created_at' => '2025-12-20 10:00:00']);

        $finance = $this->staff('admin-keuangan');
        $this->actingAs($finance)->get(route('admin.reports.monthly', ['tahun' => 1999]))
            ->assertOk()
            ->assertSee('<option value="2025"', false);
    }
}
