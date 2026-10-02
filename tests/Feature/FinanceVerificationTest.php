<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\MonthlyRevenueReport;
use App\Services\OrderService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pesanan lunas yang ditandai bukan-Keuangan (jalur lama) TIDAK dihitung
 * pendapatan sampai Keuangan mengonfirmasi; dashboard & laporan memisahkannya
 * sebagai "perlu verifikasi".
 */
class FinanceVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, string $name = 'Staf'): User
    {
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true, 'name' => $name]);
        $user->roles()->attach(Role::where('slug', $role)->first());

        return $user;
    }

    private function unpaid(string $number, float $total): Order
    {
        return Order::create([
            'order_number' => $number, 'public_token' => Str::uuid(),
            'customer_name' => 'Budi', 'customer_email' => 'budi@test.id', 'customer_phone' => '0812',
            'status' => OrderStatus::AwaitingPayment->value, 'payment_status' => PaymentStatus::Unpaid->value,
            'items_subtotal' => $total, 'tax_amount' => 0, 'grand_total' => $total, 'channel' => 'whatsapp',
        ]);
    }

    public function test_legacy_sales_marked_orders_are_excluded_until_finance_confirms(): void
    {
        $this->seed(RoleSeeder::class);
        $this->defaultWarehouse();
        $finance = $this->staff('admin-keuangan', 'Rina');
        $sales = $this->staff('admin-sales', 'Dedi');
        $orders = app(OrderService::class);

        $ok = $orders->markPaid($this->unpaid('ORD-KEU', 1_000_000), $finance);
        $gateway = $orders->markPaid($this->unpaid('ORD-GW', 2_000_000));
        $legacy = $orders->markPaid($this->unpaid('ORD-SALES', 14_400_000), $sales); // jalur lama (gate dilewati, langsung service)

        $this->assertNotNull($ok->fresh()->finance_verified_at);
        $this->assertNotNull($gateway->fresh()->finance_verified_at);
        $this->assertNull($legacy->fresh()->finance_verified_at);
        $this->assertTrue($legacy->fresh()->needsFinanceVerification());

        // Laporan: hanya 3 jt masuk pendapatan; 14,4 jt dilaporkan sebagai perlu verifikasi.
        $year = (int) now()->format('Y');
        $data = app(MonthlyRevenueReport::class)->year($year);
        $this->assertEquals(3_000_000, $data['total']['pendapatan']);
        $this->assertSame(2, $data['total']['pesanan']);
        $this->assertSame(1, $data['total']['perlu_verifikasi']);
        $this->assertEquals(14_400_000, $data['total']['perlu_verifikasi_nilai']);

        // Dashboard memakai angka yang sama.
        $this->actingAs($finance)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Rp 3.000.000')->assertSee('1 pesanan perlu verifikasi Keuangan');

        // Halaman laporan & pesanan menandainya; sales tidak melihat tombol konfirmasi.
        $this->actingAs($finance)->get(route('admin.reports.monthly', ['tahun' => $year, 'bulan' => (int) now()->format('n')]))
            ->assertOk()->assertSee('belum dikonfirmasi Keuangan')->assertSee('ORD-SALES')->assertSee('Konfirmasi');
        $this->actingAs($finance)->get(route('admin.orders.show', $legacy))->assertOk()->assertSee('Konfirmasi Verifikasi Keuangan');
        $this->actingAs($sales)->get(route('admin.orders.show', $legacy))->assertOk()
            ->assertSee('Perlu verifikasi Keuangan')->assertDontSee('Konfirmasi Verifikasi Keuangan');
        $this->actingAs($sales)->post(route('admin.orders.finance-verify', $legacy))->assertForbidden();

        // Keuangan mengonfirmasi → masuk pendapatan, tercatat di riwayat.
        $this->actingAs($finance)->post(route('admin.orders.finance-verify', $legacy), ['note' => 'Mutasi BCA 11/09'])
            ->assertRedirect()->assertSessionHas('success');
        $legacy->refresh();
        $this->assertNotNull($legacy->finance_verified_at);
        $this->assertSame($finance->id, $legacy->finance_verified_by);
        $this->assertStringContainsString('Mutasi BCA 11/09', $legacy->statusHistories()->latest('id')->first()->internal_note);

        $data = app(MonthlyRevenueReport::class)->year($year);
        $this->assertEquals(17_400_000, $data['total']['pendapatan']);
        $this->assertSame(0, $data['total']['perlu_verifikasi']);

        // Konfirmasi hanya untuk pesanan lunas.
        $this->actingAs($finance)->post(route('admin.orders.finance-verify', $this->unpaid('ORD-X', 1)))
            ->assertRedirect()->assertSessionHas('error');
    }
}
