<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function completedOrderItem(User $user, Product $product): OrderItem
    {
        $order = Order::create([
            'order_number' => 'RS-'.Str::random(5), 'public_token' => (string) Str::uuid(),
            'user_id' => $user->id, 'customer_name' => $user->name, 'customer_email' => $user->email,
            'status' => OrderStatus::Completed, 'payment_status' => 'paid',
            'items_subtotal' => 100000, 'grand_total' => 100000,
        ]);

        return $order->items()->create([
            'product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name,
            'unit_price' => 100000, 'quantity' => 1, 'line_total' => 100000,
        ]);
    }

    public function test_verified_purchaser_can_review_once(): void
    {
        $user = $this->customer();
        $product = $this->stockedProduct(10);
        $item = $this->completedOrderItem($user, $product);

        $review = app(ReviewService::class)->create($user, $item, ['rating' => 5, 'comment' => 'Bagus']);

        $this->assertTrue($review->is_verified_purchase);
        $this->assertEquals(5.0, $product->fresh()->rating_avg);
        $this->assertEquals(1, $product->fresh()->rating_count);

        // A second review for the same item is rejected.
        $this->expectException(ValidationException::class);
        app(ReviewService::class)->create($user, $item, ['rating' => 4]);
    }

    public function test_non_purchaser_cannot_review_that_item(): void
    {
        $owner = $this->customer();
        $stranger = $this->customer();
        $product = $this->stockedProduct(10);
        $item = $this->completedOrderItem($owner, $product);

        $this->expectException(ValidationException::class);
        app(ReviewService::class)->create($stranger, $item, ['rating' => 5]);
    }

    public function test_hidden_review_is_excluded_from_average(): void
    {
        $user = $this->customer();
        $product = $this->stockedProduct(10);
        $item = $this->completedOrderItem($user, $product);

        $service = app(ReviewService::class);
        $review = $service->create($user, $item, ['rating' => 5]);
        $this->assertEquals(5.0, $product->fresh()->rating_avg);

        $service->setVisibility($review, false);
        $this->assertEquals(0.0, $product->fresh()->rating_avg);
        $this->assertEquals(0, $product->fresh()->rating_count);
    }
}
