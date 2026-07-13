<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the whole /admin area: the user must be authenticated, active, and hold
 * at least one back-office role. Everything finer-grained is done per-permission.
 */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || ! $user->isStaffMember()) {
            abort(403, 'Akses admin ditolak.');
        }

        return $next($request);
    }
}
