<?php

namespace App\Http\Middleware;

use App\Services\AffiliateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When a request arrives with ?ref=CODE, remember the affiliate in a cookie
 * (last-click attribution) and log the click.
 */
class CaptureReferral
{
    public function __construct(private readonly AffiliateService $affiliates)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->query(AffiliateService::COOKIE);

        if ($request->isMethod('GET') && is_string($code) && $code !== '') {
            $this->affiliates->trackClick(trim($code), $request);
        }

        return $next($request);
    }
}
