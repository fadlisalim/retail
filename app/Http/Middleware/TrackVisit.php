<?php

namespace App\Http\Middleware;

use App\Models\SiteVisit;
use App\Services\TrafficAttribution;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records one row per browser session with its first-touch traffic source, and
 * counts further page views on that same row. Storefront GET pages only —
 * admin, API polls, webhooks and assets are ignored, as are obvious bots.
 * Tracking never breaks a request: all failures are swallowed and logged.
 */
class TrackVisit
{
    /** Paths that are never a "page visit". */
    private const IGNORED = ['admin', 'admin/*', 'api/*', 'webhook/*', 'up', 'build/*', 'storage/*'];

    public function __construct(private readonly TrafficAttribution $attribution) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($this->shouldTrack($request, $response)) {
                $this->record($request);
            }
        } catch (\Throwable $e) {
            Log::warning('Visit tracking failed: '.$e->getMessage());
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && ! $request->ajax()
            && ! $request->is(...self::IGNORED)
            && $response->getStatusCode() < 400
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && ! $this->isBot((string) $request->userAgent());
    }

    private function record(Request $request): void
    {
        $token = hash('sha256', (string) $request->session()->getId());

        $visit = SiteVisit::where('session_token', $token)->first();

        if ($visit) {
            // Same session: only the page counter and recency change — the
            // source stays as the FIRST touch that brought them in.
            $visit->increment('page_views');
            $visit->forceFill([
                'last_seen_at' => now(),
                'user_id' => $request->user()?->id ?? $visit->user_id,
            ])->save();

            return;
        }

        $attribution = $this->attribution->resolve($request);

        SiteVisit::create([
            'session_token' => $token,
            'source' => $attribution['source'],
            'medium' => $attribution['medium'],
            'campaign' => $attribution['campaign'],
            'content' => $attribution['content'],
            'referrer_host' => $attribution['referrer_host'],
            'landing_path' => \Illuminate\Support\Str::limit('/'.ltrim($request->path(), '/'), 191, ''),
            'is_mobile' => $this->isMobile((string) $request->userAgent()),
            'page_views' => 1,
            'user_id' => $request->user()?->id,
            // Hashed with the app key — enough to spot repeat abuse, never the raw IP.
            'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), (string) config('app.key')) : null,
            'created_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    private function isBot(string $userAgent): bool
    {
        if ($userAgent === '') {
            return true;
        }

        return (bool) preg_match(
            '/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegram|preview|monitor|curl|wget|headless|lighthouse|pingdom|uptime/i',
            $userAgent,
        );
    }

    private function isMobile(string $userAgent): bool
    {
        return (bool) preg_match('/android|iphone|ipad|ipod|mobile|opera mini|windows phone/i', $userAgent);
    }
}
