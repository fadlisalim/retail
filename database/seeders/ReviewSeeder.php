<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    private array $comments = [
        'Produk berkualitas, pengiriman cepat dan aman.',
        'Sangat puas, sesuai deskripsi dan bergaransi resmi.',
        'Barang original, tim CS responsif membantu instalasi.',
        'Harga bersaing, performa sesuai spesifikasi.',
        'Recommended untuk kebutuhan PLTS rumah.',
        'Packing rapi, produk berfungsi dengan baik.',
    ];

    public function run(): void
    {
        $reviews = app(ReviewService::class);
        $touched = collect();
        $pairs = collect(); // guard (product,user) duplicates

        // 1) Verified reviews from completed orders.
        $completed = Order::where('status', 'completed')->with('items')->get();
        foreach ($completed as $order) {
            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }
                Review::updateOrCreate(['order_item_id' => $item->id], [
                    'product_id' => $item->product_id,
                    'user_id' => $order->user_id,
                    'rating' => rand(4, 5),
                    'title' => 'Sesuai ekspektasi',
                    'comment' => $this->comments[array_rand($this->comments)],
                    'is_verified_purchase' => true,
                    'is_visible' => true,
                    'edit_deadline_at' => now()->addDays(7),
                ]);
                $touched->push($item->product_id);
                $pairs->push($item->product_id.'-'.$order->user_id);
            }
        }

        // 2) Top up to ~20 with additional (unverified) reviews on popular products.
        $products = Product::published()->orderByDesc('is_featured')->take(15)->get();
        $customers = User::where('is_staff', false)->get();

        while (Review::count() < 20 && $customers->isNotEmpty()) {
            $product = $products->random();
            $user = $customers->random();
            $key = $product->id.'-'.$user->id;
            if ($pairs->contains($key)) {
                continue;
            }
            $pairs->push($key);

            Review::create([
                'product_id' => $product->id,
                'user_id' => $user->id,
                'rating' => rand(3, 5),
                'title' => 'Ulasan pelanggan',
                'comment' => $this->comments[array_rand($this->comments)],
                'is_verified_purchase' => false,
                'is_visible' => true,
                'edit_deadline_at' => now()->addDays(7),
            ]);
            $touched->push($product->id);
        }

        foreach ($touched->unique() as $productId) {
            if ($product = Product::find($productId)) {
                $reviews->recomputeRating($product);
            }
        }
    }
}
