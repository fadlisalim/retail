<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews)
    {
    }

    public function index(Request $request): View
    {
        $visibility = $request->input('visibility', 'all');
        $rating = $request->input('rating');

        $query = Review::query()
            ->with(['product', 'user'])
            ->withCount('reports')
            ->latest();

        if ($visibility === 'hidden') {
            $query->where('is_visible', false);
        } elseif ($visibility === 'reported') {
            $query->whereHas('reports', fn ($q) => $q->where('resolved', false));
        }

        if ($rating !== null && $rating !== '') {
            $query->where('rating', (int) $rating);
        }

        $reviews = $query->paginate(20)->withQueryString();

        return view('admin.reviews.index', compact('reviews', 'visibility', 'rating'));
    }

    public function toggleVisibility(Request $request, Review $review): RedirectResponse
    {
        $this->reviews->setVisibility($review, ! $review->is_visible, $request->user());

        return back()->with('success', 'Visibilitas review diperbarui.');
    }

    public function reply(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'reply' => ['required', 'string', 'max:1000'],
        ]);

        $this->reviews->reply($review, $data['reply'], $request->user());

        return back()->with('success', 'Balasan berhasil disimpan.');
    }
}
