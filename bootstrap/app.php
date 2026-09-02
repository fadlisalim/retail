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
        // Di belakang reverse proxy / CDN (LiteSpeed, Cloudflare) IP asli
        // pengunjung datang lewat X-Forwarded-For. Tanpa trusted proxy semua
        // throttle per-IP menghitung IP si proxy — satu jatah (mis. 10 login/
        // menit) dibagi SELURUH pengunjung situs → 429 acak. Set
        // TRUSTED_PROXIES di .env ('*' atau daftar IP dipisah koma).
        $proxies = trim((string) env('TRUSTED_PROXIES', ''));
        if ($proxies !== '') {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

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
        // JSON dipaksa untuk /api/* (walau tanpa header Accept), dan tetap
        // dihormati untuk request lain yang memang meminta JSON — mis. fetch
        // inline-edit di halaman admin Harga & Margin, yang butuh 422 JSON,
        // bukan redirect 302.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
