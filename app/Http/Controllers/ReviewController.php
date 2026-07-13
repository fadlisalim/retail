<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews)
    {
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'order_item_id' => ['required', 'exists:order_items,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:150'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'photos.*' => ['nullable', 'image', 'max:2048'],
        ]);

        $orderItem = OrderItem::findOrFail($data['order_item_id']);
        $review = $this->reviews->create($request->user(), $orderItem, $data);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $review->media()->create([
                    'type' => 'image',
                    'path' => $photo->store('reviews', 'public'),
                ]);
            }
        }

        return back()->with('success', 'Terima kasih atas ulasan Anda.');
    }

    public function helpful(Request $request, Review $review): RedirectResponse
    {
        $this->reviews->markHelpful($review, $request->user());

        return back()->with('success', 'Terima kasih atas masukan Anda.');
    }

    public function report(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:100'],
            'detail' => ['nullable', 'string', 'max:500'],
        ]);

        $review->reports()->create([
            'user_id' => $request->user()->id,
            'reason' => $data['reason'],
            'detail' => $data['detail'] ?? null,
        ]);

        return back()->with('success', 'Laporan Anda telah dikirim ke tim moderasi.');
    }
}
