<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `permission:catalog.manage`. Beberapa izin dipisah `|`
 * berarti SALAH SATU cukup (`permission:price.manage|inventory.manage`).
 * Super-admins pass everything (handled inside hasPermission()).
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        $allowed = $user && collect(explode('|', $permission))
            ->contains(fn (string $slug) => $user->hasPermission(trim($slug)));

        if (! $allowed) {
            abort(403, 'Anda tidak memiliki izin untuk tindakan ini.');
        }

        return $next($request);
    }
}
