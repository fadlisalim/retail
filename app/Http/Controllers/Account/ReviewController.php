<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews)
    {
    }

    public function index(): View
    {
        return view('account.reviews', [
            'reviews' => auth()->user()->reviews()->with('product')->latest()->get(),
            'reviewable' => $this->reviews->reviewableItems(auth()->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_item_id' => ['required', 'exists:order_items,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:150'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->reviews->create($request->user(), OrderItem::findOrFail($data['order_item_id']), $data);

        return back()->with('success', 'Ulasan berhasil dikirim. Terima kasih!');
    }
}
