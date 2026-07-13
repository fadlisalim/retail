<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures every visitor (guest or logged-in) carries a stable cart/wishlist token
 * in the session so guest carts, wishlists and comparisons survive until login,
 * at which point they can be merged into the account.
 */
class SyncGuestSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('guest_token')) {
            $request->session()->put('guest_token', (string) Str::uuid());
        }

        return $next($request);
    }
}
