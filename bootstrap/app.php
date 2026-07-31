<?php

use App\Http\Middleware\CaptureReferral;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\SyncGuestSession;
use App\Http\Middleware\TrackVisit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every web request carries a stable guest token for cart/wishlist merging.
        $middleware->web(append: [
            SyncGuestSession::class,
            CaptureReferral::class,
            TrackVisit::class,
        ]);

        $middleware->alias([
            'staff' => EnsureUserIsStaff::class,
            'permission' => EnsurePermission::class,
        ]);

        // Gateways can't send a CSRF token; the webhook is protected by HMAC
        // signature verification inside PaymentManager instead.
        $middleware->validateCsrfTokens(except: [
            'webhook/pembayaran/*',
            'webhook/wablas',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
