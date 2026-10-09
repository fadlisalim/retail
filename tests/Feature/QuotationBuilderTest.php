<?php

namespace Tests\Feature;

use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Services\QuotationService;
use App\Services\SettingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sales menyusun penawaran sendiri: baris dari katalog + produk/jasa manual,
 * draf → kirim (nomor & revisi), ubah baris, PDF (admin & link customer), akses.
 */
class QuotationBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create(['is_staff' => true, 'is_active' => true, 'name' => 'Sales Andi']);
        $u->roles()->attach(Role::where('slug', $role)->first());

        return $u;
    }

    public function test_sales_builds_a_quotation_from_catalog_and_manual_lines_then_sends_it(): void
    {
        $settings = app(SettingService::class);
        $settings->set('tax.enabled', true, 'boolean', 'tax');
        $settings->set('tax.ppn_percent', 11, 'integer', 'tax');
        $sales = $this->staff('admin-sales');
        $product = $this->stockedProduct(10, ['name' => 'PJU Tenaga Surya 60W', 'price' => 2_000_000, 'brand_id' => null]);
        $customer = $this->customer(['name' => 'Pak Dedi', 'email' => 'dedi@test.id', 'whatsapp' => '6281200001111']);

        $this->actingAs($this->staff('admin-gudang'))->get(route('admin.quotations.create'))->assertForbidden();
        $this->actingAs($sales)->get(route('admin.quotations.create'))->assertOk()->assertSee('Susun Penawaran')->assertSee('PJU Tenaga Surya 60W');

        // Simpan sebagai draf: belum ada nomor penawaran, status dalam review, total dihitung server (PPN hanya item kena pajak).
        $this->actingAs($sales)->post(route('admin.quotations.store'), [
            'user_id' => $customer->id, 'contact_name' => 'Pak Dedi', 'contact_email' => 'dedi@test.id', 'contact_phone' => '081200001111',
            'company_name' => 'Desa Sukamaju', 'project_name' => 'PJU 20 titik',
            'items' => [
                ['product_id' => $product->id, 'name' => 'PJU Tenaga Surya 60W', 'quantity' => 20, 'unit_price' => 1_900_000, 'discount' => 0, 'is_taxable' => 1],
                ['name' => 'Jasa instalasi & tiang 7 m', 'note' => 'Termasuk pondasi cor', 'quantity' => 20, 'unit_price' => 1_250_000, 'discount' => 0, 'is_taxable' => 0],
                ['name' => 'Survei lokasi', 'quantity' => 1, 'unit_price' => 500_000, 'discount' => 500_000, 'is_taxable' => 0],
            ],
            'discount' => 1_000_000, 'shipping_cost' => 2_500_000, 'apply_tax' => 1,
            'payment_terms' => 'DP 50%, pelunasan sebelum kirim', 'valid_until' => now()->addDays(14)->format('Y-m-d'),
            'admin_note' => 'Garansi lampu 3 tahun.', 'action' => 'draft',
        ])->assertRedirect()->assertSessionHas('success');

        $q = Quotation::firstOrFail();
        $this->assertStringStartsWith('SQ-', $q->rfq_number);
        $this->assertNull($q->quotation_number);
        $this->assertSame('under_review', $q->status->value);
        $this->assertSame($customer->id, $q->user_id);
        $this->assertSame($sales->id, $q->handled_by);
        $this->assertCount(3, $q->items);
        $this->assertSame($product->id, $q->items[0]->product_id);
        $this->assertNull($q->items[1]->product_id);
        $this->assertEquals(38_000_000, $q->items[0]->line_total);
        $this->assertEquals(25_000_000, $q->items[1]->line_total);
        $this->assertEquals(0, $q->items[2]->line_total);
        $this->assertEquals(63_000_000, $q->items_subtotal);
        $this->assertEquals(round((38_000_000 - 1_000_000) * 0.11, 2), $q->tax_amount); // PPN hanya atas item kena pajak − diskon header
        $this->assertEquals(63_000_000 - 1_000_000 + 2_500_000 + 4_070_000, $q->grand_total);
        $this->assertSame(0, $q->revisions()->count());

        // Pratinjau PDF draf (watermark DRAF); link customer belum ada PDF.
        $pdf = $this->actingAs($sales)->get(route('admin.quotations.pdf', $q));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->get(route('quotations.pdf', $q->public_token))->assertNotFound();

        // Halaman detail: editor memuat baris, tombol kirim.
        $this->actingAs($sales)->get(route('admin.quotations.show', $q))->assertOk()
            ->assertSee('Jasa instalasi')->assertSee('Pratinjau PDF (draf)')->assertSee('Simpan &amp; kirim ke customer', false);

        // Ubah baris: hapus survei, tambah jasa baru, ubah qty — lalu kirim.
        $items = $q->items;
        $this->actingAs($sales)->post(route('admin.quotations.price', $q), [
            'items' => [
                ['id' => $items[0]->id, 'product_id' => $product->id, 'name' => 'PJU Tenaga Surya 60W', 'quantity' => 22, 'unit_price' => 1_900_000, 'discount' => 0, 'is_taxable' => 1],
                ['id' => $items[1]->id, 'name' => 'Jasa instalasi & tiang 7 m', 'quantity' => 22, 'unit_price' => 1_250_000, 'discount' => 0, 'is_taxable' => 0],
                ['name' => 'Pelatihan operator', 'quantity' => 1, 'unit_price' => 1_500_000, 'discount' => 0, 'is_taxable' => 0],
            ],
            'discount' => 0, 'shipping_cost' => 2_500_000, 'apply_tax' => 1, 'valid_until' => now()->addDays(14)->format('Y-m-d'), 'action' => 'send',
        ])->assertRedirect()->assertSessionHas('success');

        $q->refresh();
        $this->assertStringStartsWith('QUO-', $q->quotation_number);
        $this->assertSame('quote_sent', $q->status->value);
        $this->assertCount(3, $q->items);
        $this->assertNull($q->items()->where('name', 'Survei lokasi')->first());
        $this->assertSame(22, $q->items()->where('product_id', $product->id)->value('quantity'));
        $this->assertSame(1, $q->revisions()->count());
        $this->assertEquals(22 * 1_900_000 + 22 * 1_250_000 + 1_500_000, $q->items_subtotal);
        $this->assertEquals(round(22 * 1_900_000 * 0.11, 2), $q->tax_amount);

        // Customer: notifikasi, halaman publik + PDF; admin: tombol WA & salin link.
        $this->assertSame(1, $customer->notifications()->count());
        $this->get(route('quotations.show', $q->public_token))->assertOk()->assertSee('Unduh PDF')->assertSee($q->quotation_number);
        $public = $this->get(route('quotations.pdf', $q->public_token));
        $public->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $public->getContent());
        $this->actingAs($sales)->get(route('admin.quotations.show', $q))->assertOk()
            ->assertSee('Kirim via WhatsApp')->assertSee('wa.me/6281200001111')->assertSee('Salin link customer')->assertSee('Simpan &amp; kirim revisi', false);
        $this->actingAs($sales)->get(route('admin.quotations.index'))->assertOk()->assertSee('Susun Penawaran')->assertSee($q->rfq_number);

        // Ubah data customer dari halaman detail.
        $this->actingAs($sales)->put(route('admin.quotations.update', $q), ['contact_name' => 'Bapak Dedi Kurniawan', 'contact_phone' => '081200001111', 'company_name' => 'Pemdes Sukamaju'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Pemdes Sukamaju', $q->fresh()->company_name);

        // Konversi ke pesanan tetap jalan dengan baris jasa (tanpa produk).
        $order = app(QuotationService::class)->convertToOrder($q->fresh(), $sales);
        $this->assertCount(3, $order->items);
        $this->assertSame('QUO', $order->items->firstWhere('name', 'Pelatihan operator')->sku);
        $this->assertEquals($q->fresh()->grand_total, $order->grand_total);
    }

    public function test_validation_requires_at_least_one_line_with_name_and_price(): void
    {
        $sales = $this->staff('admin-sales');
        $this->actingAs($sales)->post(route('admin.quotations.store'), ['contact_name' => 'X', 'items' => []])->assertSessionHasErrors('items');
        $this->actingAs($sales)->post(route('admin.quotations.store'), ['contact_name' => 'X', 'items' => [['name' => '', 'quantity' => 1, 'unit_price' => '']]])
            ->assertSessionHasErrors(['items.0.name', 'items.0.unit_price']);
        $this->assertSame(0, Quotation::count());
    }

    public function test_tax_can_be_switched_off_and_draft_stays_without_number(): void
    {
        app(SettingService::class)->set('tax.enabled', true, 'boolean', 'tax');
        $sales = $this->staff('admin-sales');
        $this->actingAs($sales)->post(route('admin.quotations.store'), [
            'contact_name' => 'Ibu Sari', 'items' => [['name' => 'Panel surya 345Wp bekas proyek', 'quantity' => 10, 'unit_price' => 850_000, 'is_taxable' => 1]],
            'apply_tax' => 0, 'action' => 'draft',
        ])->assertRedirect();
        $q = Quotation::firstOrFail();
        $this->assertEquals(0, $q->tax_amount);
        $this->assertEquals(8_500_000, $q->grand_total);
        $this->assertNull($q->quotation_number);
        $this->assertSame('', $q->contact_email);
    }
}
