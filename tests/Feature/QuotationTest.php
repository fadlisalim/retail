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
            'items' => [['name' => $product->name, 'quantity' => 5, 'product_id' => $product->id]],
        ])->assertRedirect();

        $this->assertDatabaseHas('quotations', ['contact_email' => 'budi@test.id']);
    }
}
