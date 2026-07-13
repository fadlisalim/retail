<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use App\Services\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function __construct(private readonly WishlistService $wishlist)
    {
    }

    public function index(): View
    {
        $wishlist = $this->wishlist->current()->load('items.product.brand');

        return view('storefront.wishlist', ['wishlist' => $wishlist]);
    }

    public function account(): View
    {
        return $this->index();
    }

    public function toggle(Product $product): RedirectResponse
    {
        $added = $this->wishlist->toggle($product);

        return back()->with('success', $added ? 'Ditambahkan ke wishlist.' : 'Dihapus dari wishlist.');
    }

    public function share(): RedirectResponse
    {
        $token = $this->wishlist->enableSharing();

        return back()->with('success', 'Wishlist dapat dibagikan: '.route('wishlist.shared', $token));
    }

    public function shared(string $token): View
    {
        $wishlist = Wishlist::where('share_token', $token)->where('is_public', true)
            ->with('items.product.brand')->firstOrFail();

        return view('storefront.wishlist-shared', ['wishlist' => $wishlist]);
    }
}
