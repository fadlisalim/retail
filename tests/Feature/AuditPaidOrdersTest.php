<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/** php artisan pendapatan:audit — siapa yang menandai pesanan lunas (Keuangan / super admin / gateway / sales). */
class AuditPaidOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, string $name): User
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
            'items_subtotal' => $total, 'tax_amount' => 0, 'grand_total' => $total,
        ]);
    }

    public function test_lists_paid_orders_by_who_verified_them(): void
    {
        $this->seed(RoleSeeder::class);
        $this->defaultWarehouse();
        $finance = $this->staff('admin-keuangan', 'Rina Keuangan');
        $sales = $this->staff('admin-sales', 'Dedi Sales');
        $super = $this->staff('super-admin', 'Administrator');
        $orders = app(OrderService::class);

        $orders->markPaid($this->unpaid('ORD-KEU', 1_000_000), $finance);
        $orders->markPaid($this->unpaid('ORD-SUPER', 2_000_000), $super);
        $orders->markPaid($this->unpaid('ORD-GATEWAY', 3_000_000)); // webhook, tanpa aktor
        $orders->markPaid($this->unpaid('ORD-SALES', 4_000_000), $sales); // jalur lama sebelum dikunci
        $this->unpaid('ORD-BELUM', 9_000_000); // belum lunas → tidak ikut

        $this->assertSame(0, Artisan::call('pendapatan:audit', ['--tahun' => now()->format('Y')]));
        $out = Artisan::output();

        $this->assertStringContainsString('4 pesanan, total Rp 10.000.000', $out);
        $this->assertStringContainsString('ORD-SALES', $out);
        $this->assertStringContainsString('Dedi Sales (Admin Sales)', $out);
        $this->assertStringContainsString('⚠ CEK', $out);
        $this->assertStringContainsString('ORD-SUPER', $out);
        $this->assertStringContainsString('ORD-GATEWAY', $out);
        $this->assertStringContainsString('gateway / sistem', $out);
        $this->assertStringNotContainsString('ORD-KEU', $out);   // Keuangan tidak perlu dicek
        $this->assertStringNotContainsString('ORD-BELUM', $out);

        Artisan::call('pendapatan:audit', ['--tahun' => now()->format('Y'), '--semua' => true]);
        $this->assertStringContainsString('ORD-KEU', Artisan::output());
    }
}
