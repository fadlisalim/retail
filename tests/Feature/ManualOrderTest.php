<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\Terbilang;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Recording marketplace/offline sales from the admin panel. */
class ManualOrderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->first());

        return $admin;
    }

    private function enableWablas(): void
    {
        config(['services.wablas.enabled' => true, 'services.wablas.token' => 'tok', 'services.wablas.base_url' => 'https://pati.wablas.com']);
        Http::fake(['pati.wablas.com/*' => Http::response(['status' => true], 200)]);
    }

    public function test_admin_records_a_paid_tokopedia_sale_and_thanks_the_customer(): void
    {
        $this->enableWablas();
        $product = $this->stockedProduct(10, ['price' => 3329100]);

        $this->actingAs($this->admin())->post('/admin/pesanan-manual', [
            'channel' => 'tokopedia',
            'external_reference' => 'INV/20260801/MPL/123456',
            'customer_name' => 'Ussy Andira',
            'customer_phone' => '083173342644',
            'create_customer' => 1,
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 3329100]],
            'shipping_cost' => 50000,
            'discount' => 100000,
            'payment_method' => 'Tokopedia (BCA VA)',
            'mark_paid' => 1,
            'paid_at' => now()->toDateString(),
            'send_thanks' => 1,
        ])->assertRedirect();

        $order = Order::first();
        $this->assertSame('tokopedia', $order->channel);
        $this->assertSame('INV/20260801/MPL/123456', $order->external_reference);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        // 2 × 3.329.100 − 100.000 + 50.000
        $this->assertEquals(6608200, (float) $order->grand_total);
        $this->assertNotNull($order->invoice, 'Invoice harus terbit otomatis.');

        // Customer account created from just name + WhatsApp.
        $customer = User::where('is_staff', false)->where('whatsapp', '6283173342644')->first();
        $this->assertNotNull($customer);
        $this->assertSame('Ussy Andira', $customer->name);
        $this->assertSame($customer->id, $order->user_id);

        // Sold stock left the shelf.
        $this->assertSame(8, $product->fresh()->stock);

        // Thank-you WhatsApp went out once and is marked on the order.
        Http::assertSent(fn ($req) => ($req['data'][0]['phone'] ?? null) === '6283173342644'
            && str_contains($req['data'][0]['message'], 'Terima kasih')
            && str_contains($req['data'][0]['message'], $order->order_number));
        $this->assertNotNull($order->fresh()->thanks_sent_at);
    }

    public function test_thank_you_is_never_sent_twice(): void
    {
        $this->enableWablas();
        $admin = $this->admin();
        $product = $this->stockedProduct(5, ['price' => 1000000]);

        $this->actingAs($admin)->post('/admin/pesanan-manual', [
            'channel' => 'whatsapp', 'customer_name' => 'Budi', 'customer_phone' => '08123456789',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000000]],
            'mark_paid' => 1, 'send_thanks' => 1,
        ])->assertRedirect();

        $order = Order::first();
        Http::assertSentCount(1);

        // Pressing the button again changes nothing.
        $this->actingAs($admin)->post('/admin/pesanan/'.$order->public_token.'/terima-kasih')->assertRedirect();
        Http::assertSentCount(1);
    }

    public function test_unpaid_manual_order_has_no_receipt_and_no_whatsapp(): void
    {
        $this->enableWablas();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/pesanan-manual', [
            'channel' => 'offline', 'customer_name' => 'Dodi', 'customer_phone' => '08111222333',
            'items' => [['name' => 'Jasa instalasi PLTS 3 kWp', 'quantity' => 1, 'unit_price' => 4500000]],
        ])->assertRedirect();

        $order = Order::first();
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertEquals(4500000, (float) $order->grand_total);
        $this->assertSame('MANUAL', $order->items->first()->sku); // free-text line
        Http::assertNothingSent();

        // Receipt only exists once the money is in.
        $this->actingAs($admin)->get('/admin/pesanan/'.$order->public_token.'/kuitansi')->assertNotFound();
    }

    public function test_receipt_shows_amount_in_words_for_a_paid_order(): void
    {
        $admin = $this->admin();
        $product = $this->stockedProduct(3, ['price' => 1500000]);

        $this->actingAs($admin)->post('/admin/pesanan-manual', [
            'channel' => 'tokopedia', 'customer_name' => 'Sari', 'customer_phone' => '08999888777',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1500000]],
            'mark_paid' => 1, 'payment_method' => 'Transfer BNI',
        ])->assertRedirect();

        $order = Order::first();

        $this->actingAs($admin)->get('/admin/pesanan/'.$order->public_token.'/kuitansi')
            ->assertOk()
            ->assertSee('KUITANSI')
            ->assertSee('Sari')
            ->assertSee('Transfer BNI')
            ->assertSee('Satu juta lima ratus ribu rupiah');
    }

    public function test_blank_price_falls_back_to_the_catalogue_price(): void
    {
        $admin = $this->admin();
        // On promo: the sale price is what a customer would actually pay.
        $product = $this->stockedProduct(5, ['price' => 2000000, 'sale_price' => 1750000]);

        $this->actingAs($admin)->post('/admin/pesanan-manual', [
            'channel' => 'tokopedia', 'customer_name' => 'Rina', 'customer_phone' => '08222333444',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => ''],       // ikut katalog
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1500000],  // harga khusus
            ],
        ])->assertRedirect();

        $items = Order::first()->items()->orderBy('id')->get();
        $this->assertEquals(1750000, (float) $items[0]->unit_price);
        $this->assertEquals(3500000, (float) $items[0]->line_total);
        $this->assertEquals(1500000, (float) $items[1]->unit_price);
        $this->assertEquals(5000000, (float) Order::first()->items_subtotal);
    }

    public function test_free_text_item_still_requires_a_price(): void
    {
        $this->actingAs($this->admin())->post('/admin/pesanan-manual', [
            'channel' => 'offline', 'customer_name' => 'Dodi', 'customer_phone' => '08111222333',
            'items' => [['name' => 'Jasa survei lokasi', 'quantity' => 1, 'unit_price' => '']],
        ])->assertSessionHasErrors('items.0.unit_price');

        $this->assertSame(0, Order::count());
    }

    public function test_insufficient_stock_shows_a_clear_error_instead_of_a_server_error(): void
    {
        $product = $this->stockedProduct(1, ['price' => 1000000]);

        $this->actingAs($this->admin())->post('/admin/pesanan-manual', [
            'channel' => 'tokopedia', 'customer_name' => 'Tono', 'customer_phone' => '08123123123',
            'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 1000000]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
        $this->assertSame(1, $product->fresh()->stock); // stok tidak berubah
    }

    public function test_stock_can_be_skipped_for_goods_tracked_elsewhere(): void
    {
        $product = $this->stockedProduct(1, ['price' => 1000000]);

        $this->actingAs($this->admin())->post('/admin/pesanan-manual', [
            'channel' => 'tokopedia', 'customer_name' => 'Tono', 'customer_phone' => '08123123123',
            'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 1000000]],
            'skip_stock' => 1, 'mark_paid' => 1,
        ])->assertRedirect();

        $order = Order::first();
        $this->assertEquals(5000000, (float) $order->grand_total);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(1, $product->fresh()->stock); // stok sengaja tidak disentuh
    }

    /**
     * A variable product keeps its stock on the variant rows, so the line must
     * name the variant — otherwise the product-level row (usually empty) is
     * checked and a real sale is rejected as "stok tidak mencukupi".
     */
    public function test_variable_product_sells_from_the_chosen_variant(): void
    {
        $admin = $this->admin();
        $product = $this->stockedProduct(0, ['name' => 'Panel Bekas Sisa Proyek', 'product_type' => 'variable', 'price' => 300000]);
        $variant = \App\Models\ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'PANEL-BEKAS-100', 'name' => '100 Wp',
            'price' => 340000, 'is_active' => true,
        ]);
        app(\App\Services\StockService::class)->adjust(
            $product, $variant, 20, \App\Enums\StockMovementType::Purchase, note: 'stok awal varian',
        );

        // Without a variant the form must refuse, not blow up.
        $this->actingAs($admin)->post('/admin/pesanan-manual', [
            'channel' => 'tokopedia', 'customer_name' => 'Miftah', 'customer_phone' => '08170001111',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '']],
        ])->assertSessionHasErrors('items.0.variant_id');
        $this->assertSame(0, Order::count());

        // With the variant chosen the sale goes through and that variant's stock drops.
        $this->actingAs($admin)->post('/admin/pesanan-manual', [
            'channel' => 'tokopedia', 'customer_name' => 'Miftah', 'customer_phone' => '08170001111',
            'items' => [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => '']],
            'mark_paid' => 1,
        ])->assertRedirect();

        $item = Order::first()->items()->first();
        $this->assertSame($variant->id, $item->product_variant_id);
        $this->assertSame('PANEL-BEKAS-100', $item->sku);
        $this->assertEquals(340000, (float) $item->unit_price); // harga varian
        $this->assertSame(18, $variant->fresh()->stock);
    }

    public function test_variant_must_belong_to_the_selected_product(): void
    {
        $admin = $this->admin();
        $productA = $this->stockedProduct(5, ['price' => 100000]);
        $productB = $this->stockedProduct(5, ['price' => 100000]);
        $foreign = \App\Models\ProductVariant::create([
            'product_id' => $productB->id, 'sku' => 'LAIN-1', 'name' => 'Varian Lain', 'price' => 1, 'is_active' => true,
        ]);

        $this->actingAs($admin)->post('/admin/pesanan-manual', [
            'channel' => 'offline', 'customer_name' => 'X', 'customer_phone' => '08123000111',
            'items' => [['product_id' => $productA->id, 'variant_id' => $foreign->id, 'quantity' => 1, 'unit_price' => 100000]],
        ])->assertSessionHasErrors('items.0.variant_id');

        $this->assertSame(0, Order::count());
    }

    public function test_admin_can_set_stock_per_variant(): void
    {
        $admin = $this->admin();
        $product = $this->stockedProduct(0, ['product_type' => 'variable', 'price' => 100000]);
        $variant = \App\Models\ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'VAR-1', 'name' => '50 Wp', 'price' => 100000, 'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.stock.adjust', $product), [
            'variant_id' => $variant->id, 'mode' => 'set', 'amount' => 20, 'type' => 'adjustment',
        ])->assertRedirect();

        $this->assertSame(20, $variant->fresh()->stock);
    }

    public function test_existing_customer_is_reused_by_phone_number(): void
    {
        $admin = $this->admin();
        $existing = User::factory()->create(['name' => 'Pelanggan Lama', 'whatsapp' => '6281234567890', 'is_staff' => false]);

        $this->actingAs($admin)->post('/admin/pesanan-manual', [
            'channel' => 'shopee', 'customer_name' => 'Pelanggan Lama', 'customer_phone' => '081234567890',
            'create_customer' => 1,
            'items' => [['name' => 'Kabel PV 30m', 'quantity' => 1, 'unit_price' => 450000]],
        ])->assertRedirect();

        $this->assertSame($existing->id, Order::first()->user_id);
        $this->assertSame(1, User::where('whatsapp', '6281234567890')->count());
    }

    public function test_manual_order_requires_items_and_customer_contact(): void
    {
        $this->actingAs($this->admin())->post('/admin/pesanan-manual', ['channel' => 'tokopedia'])
            ->assertSessionHasErrors(['customer_name', 'customer_phone', 'items']);

        $this->assertSame(0, Order::count());
    }

    public function test_terbilang_spells_rupiah_amounts(): void
    {
        $this->assertSame('Enam juta enam ratus delapan ribu dua ratus rupiah', Terbilang::rupiah(6608200));
        $this->assertSame('Seribu rupiah', Terbilang::rupiah(1000));
        $this->assertSame('Sebelas ribu lima ratus rupiah', Terbilang::rupiah(11500));
        $this->assertSame('Nol rupiah', Terbilang::rupiah(0));
    }
}
