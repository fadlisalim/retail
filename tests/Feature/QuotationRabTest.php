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
 * RAB penawaran: modal per baris (otomatis dari katalog / diisi manual), margin,
 * gerbang review (margin < minimum / modal kosong → wajib disetujui GM), persetujuan
 * gugur bila harga/modal berubah, dan modal tidak pernah bocor ke customer.
 */
class QuotationRabTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, string $name): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create(['is_staff' => true, 'is_active' => true, 'name' => $name]);
        $u->roles()->attach(Role::where('slug', $role)->first());

        return $u;
    }

    private function payload(array $items, string $action): array
    {
        return [
            'contact_name' => 'Pak Dedi', 'contact_email' => 'dedi@test.id', 'contact_phone' => '081200001111',
            'items' => $items, 'discount' => 0, 'shipping_cost' => 0, 'apply_tax' => 0,
            'valid_until' => now()->addDays(14)->format('Y-m-d'), 'action' => $action,
        ];
    }

    public function test_low_margin_needs_gm_approval_and_approval_resets_when_prices_change(): void
    {
        app(SettingService::class)->set('quotation.min_margin_percent', '15', 'string', 'quotation');
        $sales = $this->staff('admin-sales', 'Sales Andi');
        $gm = $this->staff('super-admin', 'GM Budi');
        $product = $this->stockedProduct(10, ['name' => 'Inverter Hybrid 6kW', 'price' => 10_000_000, 'cost_price' => 9_000_000]);

        // Editor memuat modal katalog (untuk isi otomatis) — hanya di halaman admin.
        $this->actingAs($sales)->get(route('admin.quotations.create'))->assertOk()->assertSee('Modal / unit')->assertSee('9000000');

        // Kirim langsung: modal kosong di baris katalog → ambil modal katalog; jasa tanpa modal → ditahan, disimpan draf.
        $this->actingAs($sales)->post(route('admin.quotations.store'), $this->payload([
            ['product_id' => $product->id, 'name' => 'Inverter Hybrid 6kW', 'quantity' => 2, 'unit_price' => 10_000_000, 'unit_cost' => '', 'is_taxable' => 1],
            ['name' => 'Jasa instalasi', 'quantity' => 1, 'unit_price' => 3_000_000, 'unit_cost' => '', 'is_taxable' => 0],
        ], 'send'))->assertRedirect()->assertSessionHas('error');

        $q = Quotation::firstOrFail();
        $this->assertNull($q->quotation_number);
        $this->assertSame('under_review', $q->status->value);
        $this->assertCount(2, $q->items);
        $this->assertEquals(9_000_000, $q->items[0]->unit_cost);
        $this->assertNull($q->items[1]->unit_cost);
        $this->assertSame(1, $q->rab()['missing']);
        $this->assertSame(0, $q->revisions()->count());
        $this->assertStringContainsString('1 baris belum ada modalnya', session('error'));

        // Isi modal jasa → margin (23 jt − 20,5 jt) / 23 jt = 10,9% < 15% → tetap ditahan.
        $items = $q->items;
        $lines = [
            ['id' => $items[0]->id, 'product_id' => $product->id, 'name' => 'Inverter Hybrid 6kW', 'quantity' => 2, 'unit_price' => 10_000_000, 'unit_cost' => 9_000_000, 'is_taxable' => 1],
            ['id' => $items[1]->id, 'name' => 'Jasa instalasi', 'quantity' => 1, 'unit_price' => 3_000_000, 'unit_cost' => 2_500_000, 'is_taxable' => 0],
        ];
        $this->actingAs($sales)->post(route('admin.quotations.price', $q), $this->payload($lines, 'send'))->assertSessionHas('error');
        $this->assertStringContainsString('margin 10,9% di bawah minimum 15%', session('error'));
        $q->refresh();
        $this->assertNull($q->quotation_number);
        $this->assertEquals(20_500_000, $q->cost_total);
        $this->assertSame(10.9, $q->rab()['percent']);

        // Halaman detail: panel RAB, sales tidak punya tombol setujui; GM punya.
        $this->actingAs($sales)->get(route('admin.quotations.show', $q))->assertOk()
            ->assertSee('RAB &amp; Margin', false)->assertSee('Perlu persetujuan GM/atasan')->assertDontSee('Setujui RAB</button>', false);
        $this->actingAs($sales)->post(route('admin.quotations.approve-rab', $q))->assertForbidden();
        $this->actingAs($gm)->get(route('admin.quotations.show', $q))->assertOk()->assertSee('Setujui RAB</button>', false);
        $this->actingAs($gm)->post(route('admin.quotations.approve-rab', $q), ['rab_note' => 'OK, proyek strategis'])->assertSessionHas('success');
        $q->refresh();
        $this->assertTrue($q->rabApproved());
        $this->assertSame($gm->id, $q->rab_approved_by);

        // Harga diubah sales → persetujuan gugur, kirim ditahan lagi.
        $changed = $lines;
        $changed[1]['unit_price'] = 2_800_000;
        $this->actingAs($sales)->post(route('admin.quotations.price', $q), $this->payload($changed, 'draft'))->assertSessionHas('success');
        $this->assertFalse($q->fresh()->rabApproved());
        $this->actingAs($gm)->post(route('admin.quotations.approve-rab', $q))->assertSessionHas('success');

        // Tanpa perubahan setelah disetujui → sales bisa kirim.
        $this->actingAs($sales)->post(route('admin.quotations.price', $q), $this->payload($changed, 'send'))->assertSessionHas('success');
        $q->refresh();
        $this->assertNotNull($q->quotation_number);
        $this->assertSame('quote_sent', $q->status->value);
        $this->assertTrue($q->rabApproved());

        // Modal tidak pernah tampil ke customer: halaman publik, PDF, snapshot revisi.
        $this->get(route('quotations.show', $q->public_token))->assertOk()->assertDontSee('9.000.000')->assertDontSee('Modal');
        $snapshot = $q->revisions()->first()->snapshot;
        $this->assertArrayNotHasKey('unit_cost', $snapshot['items'][0]);
        $this->assertArrayNotHasKey('cost_total', $snapshot);
        $this->assertStringNotContainsString('unit_cost', json_encode($q->fresh('items')->toArray()));

        // Daftar penawaran menampilkan margin internal + tanda RAB disetujui.
        $this->actingAs($sales)->get(route('admin.quotations.index'))->assertOk()->assertSee('RAB ✓');
    }

    public function test_healthy_margin_sends_directly_and_gm_sending_low_margin_counts_as_approval(): void
    {
        app(SettingService::class)->set('quotation.min_margin_percent', '15', 'string', 'quotation');
        $sales = $this->staff('admin-sales', 'Sales Andi');
        $gm = $this->staff('super-admin', 'GM Budi');

        // Margin 25% → langsung terkirim tanpa persetujuan.
        $this->actingAs($sales)->post(route('admin.quotations.store'), $this->payload([
            ['name' => 'Panel surya 550Wp', 'quantity' => 10, 'unit_price' => 2_000_000, 'unit_cost' => 1_500_000, 'is_taxable' => 1],
        ], 'send'))->assertSessionHas('success');
        $q = Quotation::firstOrFail();
        $this->assertNotNull($q->quotation_number);
        $this->assertFalse($q->rabApproved());
        $this->assertSame(25.0, $q->rab()['percent']);

        // GM mengirim penawaran margin rendah → tercatat sebagai persetujuan GM.
        $this->actingAs($gm)->post(route('admin.quotations.store'), $this->payload([
            ['name' => 'Baterai 10kWh', 'quantity' => 1, 'unit_price' => 30_000_000, 'unit_cost' => 29_000_000, 'is_taxable' => 1],
        ], 'send'))->assertSessionHas('success');
        $low = Quotation::latest('id')->firstOrFail();
        $this->assertNotNull($low->quotation_number);
        $this->assertSame($gm->id, $low->rab_approved_by);

        // Pengaturan margin minimum bisa diubah admin.
        $this->actingAs($gm)->put(route('admin.settings.update'), ['tax_ppn_percent' => 11, 'quotation_min_margin_percent' => 20])->assertSessionHasNoErrors();
        $this->assertSame(20.0, app(QuotationService::class)->minMarginPercent());
    }

    public function test_team_reviews_rab_through_secret_link_without_login(): void
    {
        app(SettingService::class)->set('quotation.min_margin_percent', '15', 'string', 'quotation');
        $sales = $this->staff('admin-sales', 'Sales Andi');

        // Margin 10% → kirim ditahan, tersimpan draf.
        $this->actingAs($sales)->post(route('admin.quotations.store'), $this->payload([
            ['name' => 'Inverter Hybrid 6kW', 'quantity' => 2, 'unit_price' => 10_000_000, 'unit_cost' => 9_000_000, 'is_taxable' => 1],
        ], 'send'))->assertSessionHas('error');
        $q = Quotation::firstOrFail();
        $this->assertStringContainsString('bagikan link review RAB', session('error'));

        // Sales membuat link → tampil dengan tombol salin & bagikan WA.
        $this->actingAs($sales)->post(route('admin.quotations.rab-link', $q))->assertSessionHas('success');
        $q->refresh();
        $this->assertSame(40, strlen($q->rab_token));
        $link = route('rab.show', $q->rab_token);
        $this->actingAs($sales)->get(route('admin.quotations.show', $q))->assertOk()->assertSee($link)->assertSee('Bagikan via WA');
        auth()->logout();

        // Tanpa login: halaman review memuat modal & margin; token salah → 404; link customer tidak memuat token.
        $this->get($link)->assertOk()->assertSee('Review RAB internal')->assertSee('Rp 18.000.000')->assertSee('10%')->assertSee('Setujui RAB');
        $this->get(route('rab.show', str_repeat('x', 40)))->assertNotFound();
        $this->get(route('rab.show', 'pendek'))->assertNotFound();
        $this->assertStringNotContainsString($q->rab_token, json_encode($q->toArray()));

        // Minta revisi wajib catatan.
        $this->post(route('rab.review', $q->rab_token), ['reviewer_name' => 'Budi', 'division' => 'purchasing', 'decision' => 'revision'])->assertSessionHasErrors('note');
        $this->post(route('rab.review', $q->rab_token), ['reviewer_name' => 'Budi', 'division' => 'purchasing', 'decision' => 'revision', 'note' => 'Harga inverter supplier naik'])->assertRedirect($link);
        $this->assertFalse($q->fresh()->rabApproved());
        $this->assertSame(1, $sales->notifications()->count());

        // Engineer menyetujui → RAB disetujui atas namanya, sales bisa kirim tanpa GM.
        $this->post(route('rab.review', $q->rab_token), ['reviewer_name' => 'Citra', 'division' => 'engineering', 'decision' => 'approved', 'note' => 'Spek sesuai'])->assertSessionHas('success');
        $q->refresh();
        $this->assertTrue($q->rabApproved());
        $this->assertSame('Citra (Engineering)', $q->rabApproverName());
        $this->assertNull($q->rab_approved_by);
        $this->get($link)->assertOk()->assertSee('RAB disetujui Citra (Engineering)')->assertSee('Harga inverter supplier naik')->assertSee('value="Citra"', false);

        $items = $q->items;
        $lines = [['id' => $items[0]->id, 'name' => 'Inverter Hybrid 6kW', 'quantity' => 2, 'unit_price' => 10_000_000, 'unit_cost' => 9_000_000, 'is_taxable' => 1]];
        $this->actingAs($sales)->get(route('admin.quotations.show', $q))->assertSee('RAB disetujui Citra (Engineering)')->assertSee('Spek sesuai');
        $this->actingAs($sales)->post(route('admin.quotations.price', $q), $this->payload($lines, 'send'))->assertSessionHas('success');
        $this->assertNotNull($q->fresh()->quotation_number);

        // Ganti link → link lama mati.
        $old = $q->fresh()->rab_token;
        $this->actingAs($sales)->post(route('admin.quotations.rab-link', $q), ['renew' => 1])->assertSessionHas('success');
        $this->assertNotSame($old, $q->fresh()->rab_token);
        auth()->logout();
        $this->get(route('rab.show', $old))->assertNotFound();
    }
}
