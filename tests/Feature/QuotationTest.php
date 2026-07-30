<?php

namespace Tests\Feature;

use App\Enums\QuotationStatus;
use App\Models\Product;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rfq_can_be_created_and_converted_to_order(): void
    {
        $product = $this->stockedProduct(50, ['price' => 2000000]);
        $service = app(QuotationService::class);

        $quotation = $service->createRfq(
            ['contact_name' => 'PT Proyek', 'contact_email' => 'proyek@test.id', 'project_name' => 'PLTS Atap'],
            [['name' => $product->name, 'quantity' => 10, 'product_id' => $product->id]],
        );

        $this->assertEquals(QuotationStatus::New, $quotation->status);
        $this->assertCount(1, $quotation->items);

        // Admin prices it.
        $quotation = $service->price($quotation, [
            ['id' => $quotation->items->first()->id, 'unit_price' => 1800000, 'is_taxable' => true],
        ], ['shipping_cost' => 500000]);

        $this->assertEquals(QuotationStatus::QuoteSent, $quotation->status);
        $this->assertEquals(18000000, $quotation->items_subtotal); // 1.8jt x 10
        $this->assertNotNull($quotation->quotation_number);

        // Convert to a payable order.
        $order = $service->convertToOrder($quotation);
        $this->assertEquals(QuotationStatus::Converted, $quotation->fresh()->status);
        $this->assertEquals($order->id, $quotation->fresh()->converted_order_id);
        $this->assertCount(1, $order->items);
    }

    public function test_storefront_rfq_submission_creates_quotation(): void
    {
        $product = Product::factory()->quotationOnly()->create();

        $this->post('/permintaan-penawaran', [
            'contact_name' => 'Budi', 'contact_email' => 'budi@test.id',
            'project_type' => 'plts_rumah', 'project_status' => 'planning',
            'project_location' => 'Bandung, Jawa Barat', 'budget_range' => '25_50',
            'items' => [['name' => $product->name, 'quantity' => 5, 'product_id' => $product->id]],
        ])->assertRedirect();

        $this->assertDatabaseHas('quotations', ['contact_email' => 'budi@test.id']);
    }

    public function test_intake_stores_qualification_and_type_specific_requirements(): void
    {
        $product = Product::factory()->quotationOnly()->create();

        $this->post('/permintaan-penawaran', [
            'contact_name' => 'Muhammad Azwar', 'contact_email' => 'azwar@test.id', 'contact_phone' => '085398340701',
            'requester_role' => 'government', 'decision_role' => 'decision_maker',
            'project_name' => 'Pamsimas', 'project_type' => 'pompa_air', 'project_status' => 'awarded',
            'project_location' => 'Kab. Kepulauan Selayar, Sulawesi Selatan',
            'funding_source' => 'government', 'budget_range' => '150_500',
            'unit_scale' => '±150 KK', 'needs_installation' => 1, 'needs_tender_docs' => 1,
            'requirements' => [
                'total_head' => '150',
                'water_demand' => '10 m3/hari',
                'water_source' => 'Sumur bor',
                'pipe_distance' => '',            // blank → dropped
                'roof_type' => 'Genteng',          // belongs to another type → dropped
            ],
            'items' => [['name' => $product->name, 'quantity' => 2, 'product_id' => $product->id]],
        ])->assertRedirect();

        $quotation = \App\Models\Quotation::firstWhere('contact_email', 'azwar@test.id');
        $this->assertSame('government', $quotation->requester_role);
        $this->assertSame('awarded', $quotation->project_status);
        $this->assertSame('150_500', $quotation->budget_range);
        $this->assertTrue($quotation->needs_tender_docs);
        $this->assertFalse($quotation->needs_survey);

        // Only this project type's answered fields are kept.
        $this->assertSame(
            ['total_head' => '150', 'water_demand' => '10 m3/hari', 'water_source' => 'Sumur bor'],
            $quotation->requirements,
        );

        // Awarded tender + decision maker + big budget + APBD = hottest lead.
        $this->assertSame('bg-red-100 text-red-700', \App\Support\QuotationForm::priority($quotation)['class']);
    }

    public function test_intake_requires_project_type_status_location_and_budget(): void
    {
        $product = Product::factory()->quotationOnly()->create();

        $this->post('/permintaan-penawaran', [
            'contact_name' => 'Budi', 'contact_email' => 'budi@test.id',
            'project_type' => 'bukan-jenis', // invalid choice
            'items' => [['name' => $product->name, 'quantity' => 1, 'product_id' => $product->id]],
        ])->assertSessionHasErrors(['project_type', 'project_status', 'project_location', 'budget_range']);

        $this->assertSame(0, \App\Models\Quotation::count());
    }

    public function test_admin_sees_intake_details_on_the_rfq_page(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = \App\Models\User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(\App\Models\Role::where('slug', 'super-admin')->first());

        $quotation = app(QuotationService::class)->createRfq([
            'contact_name' => 'Azwar', 'contact_email' => 'azwar@test.id',
            'project_type' => 'pompa_air', 'project_status' => 'awarded', 'funding_source' => 'government',
            'budget_range' => '150_500', 'requester_role' => 'government', 'decision_role' => 'decision_maker',
            'needs_tender_docs' => true, 'unit_scale' => '±150 KK',
            'requirements' => ['total_head' => '150', 'water_source' => 'Sumur bor'],
        ], [['name' => 'Pompa surya', 'quantity' => 1]]);

        $this->actingAs($admin)->get(route('admin.quotations.show', $quotation))
            ->assertOk()
            ->assertSee('Panitia pengadaan instansi')           // requester role label
            ->assertSee('Sudah menang tender / terbit SPK')      // project status label
            ->assertSee('APBN / APBD / Dana Desa')               // funding label
            ->assertSee('Total head / ketinggian angkat (meter)') // technical label
            ->assertSee('150')
            ->assertSee('Dokumen tender');
    }
}
