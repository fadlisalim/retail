<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\InvoiceService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Edit data invoice & kuitansi (perusahaan + PIC), terkunci setelah
 * transaksi tutup.
 */
class InvoiceEditTest extends TestCase
{
    use RefreshDatabase;

    private function sales(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'admin-sales')->first());

        return $user;
    }

    private function paidOrder(string $status = 'processing'): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-INVEDIT-'.Str::upper(Str::random(4)),
            'public_token' => Str::uuid(),
            'customer_name' => 'Budi Perorangan',
            'customer_email' => 'budi@test.id',
            'customer_phone' => '08123',
            'status' => $status,
            'payment_status' => PaymentStatus::Paid->value,
            'items_subtotal' => 5_000_000,
            'tax_amount' => 0,
            'grand_total' => 5_000_000,
            'paid_at' => now(),
        ]);
        app(InvoiceService::class)->createForOrder($order);

        return $order->fresh('invoice');
    }

    public function test_sales_can_set_company_and_pic_and_they_show_on_all_documents(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->sales())
            ->put(route('admin.orders.invoice.update', $order), [
                'name' => 'Budi Perorangan',
                'company' => 'PT Maju Energi Nusantara',
                'pic' => 'Ibu Sari (Finance)',
                'phone' => '08123', 'email' => 'budi@test.id',
                'address' => 'Jl. Merdeka 1, Jakarta', 'npwp' => '01.111.222.3-444.000',
            ])->assertRedirect()->assertSessionHas('success');

        $snap = (array) $order->fresh()->invoice->customer_snapshot;
        $this->assertSame('PT Maju Energi Nusantara', $snap['company']);
        $this->assertSame('Ibu Sari (Finance)', $snap['pic']);

        // Invoice publik: perusahaan tampil, PIC sebagai u.p.
        $this->get(route('invoices.show', $order->invoice->public_token))
            ->assertOk()
            ->assertSee('PT Maju Energi Nusantara')
            ->assertSee('u.p. Ibu Sari (Finance)');

        // Kuitansi (butuh payment.manage → pakai super-admin).
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->first());
        $this->actingAs($admin)
            ->get(route('admin.orders.receipt', $order))
            ->assertOk()
            ->assertSee('PT Maju Energi Nusantara')
            ->assertSee('u.p. Ibu Sari (Finance)');
    }

    /** Baris barang dokumen bisa disunting tanpa menyentuh order_items. */
    public function test_document_line_items_can_be_edited_without_touching_the_order(): void
    {
        $order = $this->paidOrder();
        $order->items()->create([
            'product_id' => null, 'sku' => 'MANUAL', 'name' => 'Inverter 5kW',
            'unit_price' => 5_000_000, 'quantity' => 1, 'line_total' => 5_000_000,
        ]);

        $this->actingAs($this->sales())
            ->put(route('admin.orders.invoice.update', $order), [
                'name' => 'Budi Perorangan',
                'items' => [
                    ['name' => 'Inverter Hybrid 5kW (termasuk instalasi)', 'quantity' => 1, 'unit_price' => 4_500_000],
                    ['name' => 'Jasa komisioning & training', 'quantity' => 2, 'unit_price' => 250_000],
                ],
            ])->assertRedirect()->assertSessionHas('success');

        $invoice = $order->fresh()->invoice;
        // Dokumen berubah: 4,5jt + 2×250rb = 5jt.
        $this->assertCount(2, $invoice->lineItems());
        $this->assertEquals(5_000_000, (float) $invoice->subtotal);
        $this->assertEquals(5_000_000, (float) $invoice->total);
        // Item pesanan asli tidak tersentuh.
        $this->assertSame('Inverter 5kW', $order->items()->first()->name);

        // Ketiga dokumen menampilkan baris hasil suntingan.
        $this->get(route('invoices.show', $invoice->public_token))
            ->assertOk()
            ->assertSee('Inverter Hybrid 5kW (termasuk instalasi)')
            ->assertSee('Jasa komisioning')
            ->assertDontSee('>Inverter 5kW<', false);

        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->first());
        $this->actingAs($admin)
            ->get(route('admin.orders.receipt', $order))
            ->assertOk()
            ->assertSee('Jasa komisioning');
    }

    public function test_documents_are_locked_once_the_order_is_confirmed_done(): void
    {
        $order = $this->paidOrder(OrderStatus::Completed->value);

        $this->actingAs($this->sales())
            ->put(route('admin.orders.invoice.update', $order), [
                'name' => 'Diubah Setelah Selesai',
            ])->assertSessionHasErrors('invoice');

        $this->assertSame('Budi Perorangan', $order->fresh()->invoice->customer_snapshot['name']);

        // Halaman pesanan menampilkan penanda terkunci, bukan tombol edit.
        $this->actingAs($this->sales())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Terkunci')
            ->assertDontSee('Edit Data Dokumen');
    }

    public function test_without_a_company_the_documents_fall_back_to_the_person_name(): void
    {
        $order = $this->paidOrder();

        $this->get(route('invoices.show', $order->invoice->public_token))
            ->assertOk()
            ->assertSee('Budi Perorangan')
            ->assertDontSee('u.p.');
    }

    public function test_finance_without_order_manage_cannot_edit(): void
    {
        $this->seed(RoleSeeder::class);
        $keuangan = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $keuangan->roles()->attach(Role::where('slug', 'admin-keuangan')->first());
        $order = $this->paidOrder();

        $this->actingAs($keuangan)
            ->put(route('admin.orders.invoice.update', $order), ['name' => 'X'])
            ->assertForbidden();
    }
}
