<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductSlugHistory;
use App\Models\ProductView;
use App\Models\RecentlyViewedProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $product = Product::where('slug', $slug)->first();

        // Old shared link? 301 to the current slug so links never break (spec §1/§29).
        if (! $product) {
            $history = ProductSlugHistory::where('old_slug', $slug)->latest()->first();
            if ($history && ($current = Product::find($history->product_id))) {
                return redirect()->route('products.show', $current->slug, 301);
            }
            abort(404);
        }

        abort_unless($product->status === 'published', 404);

        $product->load([
            'brand', 'category.parent', 'variants', 'images', 'documents', 'videos',
            'attributeValues.attribute.group', 'conditionDetail',
            'bundleItems.component.brand',
            'questions' => fn ($q) => $q->where('is_visible', true)->with('answers'),
        ]);

        $this->recordView($request, $product);

        $reviews = $product->visibleReviews()->with(['user', 'media'])->latest()->paginate(5);
        $reviewService = app(\App\Services\ReviewService::class);

        $breadcrumbs = [];
        foreach ($product->category?->ancestors() ?? [] as $c) {
            $breadcrumbs[] = ['label' => $c->name, 'url' => route('categories.show', $c->slug)];
        }
        $breadcrumbs[] = ['label' => $product->name];

        return view('storefront.product', [
            'product' => $product,
            'reviews' => $reviews,
            'ratingDistribution' => $reviewService->distribution($product),
            'related' => $this->related($product),
            'similar' => $this->similar($product),
            'boughtTogether' => $this->boughtTogether($product),
            'recentlyViewed' => $this->recentlyViewed($request, $product),
            'breadcrumbs' => $breadcrumbs,
            'canReview' => $this->userCanReview($product),
        ]);
    }

    public function ask(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:5', 'max:1000'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $product->questions()->create([
            'user_id' => auth()->id(),
            'name' => auth()->user()?->name ?? $data['name'] ?? 'Anonim',
            'question' => $data['question'],
            'is_visible' => true,
        ]);

        return back()->with('success', 'Pertanyaan Anda telah dikirim dan akan dijawab oleh tim kami.');
    }

    private function recordView(Request $request, Product $product): void
    {
        $token = session('guest_token');
        $product->increment('view_count');

        ProductView::create([
            'product_id' => $product->id,
            'user_id' => auth()->id(),
            'session_token' => $token,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        RecentlyViewedProduct::updateOrCreate(
            array_filter([
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'session_token' => auth()->check() ? null : $token,
            ], fn ($v) => $v !== null) + ['product_id' => $product->id],
            ['viewed_at' => now()],
        );
    }

    private function related(Product $product)
    {
        return Product::published()->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->with(['brand', 'category'])->take(8)->get();
    }

    private function similar(Product $product)
    {
        return Product::published()->where('id', '!=', $product->id)
            ->where('brand_id', $product->brand_id)
            ->with(['brand', 'category'])->take(8)->get();
    }

    private function boughtTogether(Product $product)
    {
        // Components of any bundle this product belongs to, else same-category picks.
        return Product::published()->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->orderByDesc('sold_count')->with(['brand'])->take(4)->get();
    }

    private function recentlyViewed(Request $request, Product $product)
    {
        $query = RecentlyViewedProduct::where('product_id', '!=', $product->id)
            ->when(auth()->check(),
                fn ($q) => $q->where('user_id', auth()->id()),
                fn ($q) => $q->where('session_token', session('guest_token')))
            ->latest('viewed_at')->take(config('rekasurya.catalog.recently_viewed_max', 12))->pluck('product_id');

        return Product::published()->whereIn('id', $query)->with('brand')->get();
    }

    private function userCanReview(Product $product): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return \App\Models\OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->where('user_id', auth()->id())->where('status', 'completed'))
            ->whereDoesntHave('review')
            ->exists();
    }
}
