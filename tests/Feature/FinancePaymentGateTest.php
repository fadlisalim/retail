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
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Opsi A: pelunasan (= kuitansi terbit = masuk laporan pendapatan) hanya oleh
 * Keuangan (payment.manage) atau super admin. Sales tidak bisa menandai lunas
 * lewat status "Pembayaran Diverifikasi" maupun centang "Sudah dibayar" di
 * pesanan manual; payment gateway (tanpa aktor) tetap otomatis.
 */
class FinancePaymentGateTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', $role)->first());

        return $user;
    }

    private function unpaidOrder(): Order
    {
        return Order::create([
            'order_number' => 'ORD-GATE-'.Str::upper(Str::random(4)), 'public_token' => Str::uuid(),
            'customer_name' => 'Budi', 'customer_email' => 'budi@test.id', 'customer_phone' => '0812',
            'status' => OrderStatus::AwaitingPayment->value, 'payment_status' => PaymentStatus::Unpaid->value,
            'items_subtotal' => 1_000_000, 'tax_amount' => 0, 'grand_total' => 1_000_000,
        ]);
    }

    public function test_sales_cannot_mark_paid_through_the_status_form(): void
    {
        $order = $this->unpaidOrder();
        $sales = $this->staff('admin-sales');

        $this->actingAs($sales)
            ->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.status', $order), ['status' => 'payment_verified'])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Hanya Keuangan'));

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);

        // Dropdown-nya menandai opsi itu khusus Keuangan.
        $this->actingAs($sales)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('(hanya Keuangan)')
            ->assertDontSee('Verifikasi Lunas &amp; Terbitkan Kuitansi', false);
    }

    public function test_finance_and_super_admin_can_mark_paid(): void
    {
        $order = $this->unpaidOrder();
        $finance = $this->staff('admin-keuangan');

        $this->actingAs($finance)->get(route('admin.orders.show', $order))
            ->assertOk()->assertSee('Verifikasi Lunas &amp; Terbitkan Kuitansi', false);
        $this->actingAs($finance)->post(route('admin.orders.verify', $order))->assertRedirect();
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);

        $order2 = $this->unpaidOrder();
        $super = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $super->roles()->attach(Role::where('slug', 'super-admin')->first());
        app(OrderService::class)->changeStatus($order2, OrderStatus::PaymentVerified, $super);
        $this->assertSame(PaymentStatus::Paid, $order2->fresh()->payment_status);

        // Tanpa aktor (payment gateway / sistem) tetap boleh.
        $order3 = $this->unpaidOrder();
        app(OrderService::class)->changeStatus($order3, OrderStatus::PaymentVerified);
        $this->assertSame(PaymentStatus::Paid, $order3->fresh()->payment_status);
    }

    public function test_service_rejects_a_sales_actor_directly(): void
    {
        $order = $this->unpaidOrder();
        $sales = $this->staff('admin-sales');

        $this->expectException(ValidationException::class);
        app(OrderService::class)->changeStatus($order, OrderStatus::PaymentVerified, $sales);
    }

    public function test_sales_manual_order_cannot_be_saved_as_already_paid(): void
    {
        $this->defaultWarehouse();
        $product = $this->stockedProduct(5, ['price' => 2_000_000]);
        $sales = $this->staff('admin-sales');

        $this->actingAs($sales)->get(route('admin.orders.create'))
            ->assertOk()
            ->assertDontSee('name="mark_paid"', false)
            ->assertSee('Keuangan yang menandai lunas');

        $payload = [
            'customer_name' => 'Pak Andi', 'customer_phone' => '08123', 'channel' => 'whatsapp',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'mark_paid' => 1,
        ];
        $this->actingAs($sales)->post(route('admin.orders.store'), $payload)->assertSessionHasErrors('mark_paid');
        $this->assertSame(0, Order::count());

        // Tanpa mark_paid: tersimpan sebagai belum dibayar.
        unset($payload['mark_paid']);
        $this->actingAs($sales)->post(route('admin.orders.store'), $payload)->assertRedirect();
        $this->assertSame(PaymentStatus::Unpaid, Order::first()->payment_status);

        // Super admin (punya payment.manage) boleh langsung lunas.
        $super = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $super->roles()->attach(Role::where('slug', 'super-admin')->first());
        $this->actingAs($super)->post(route('admin.orders.store'), $payload + ['mark_paid' => 1])->assertRedirect();
        $this->assertSame(PaymentStatus::Paid, Order::latest('id')->first()->payment_status);
    }
}
