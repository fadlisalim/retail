<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tiap rute ber-throttle punya jatah sendiri. Tanpa prefix, Laravel memakai satu
 * hitungan per IP untuk SEMUA rute ber-throttle — pembeli yang sering mengetik di
 * pencarian/cek ongkir bisa ditolak (429) saat checkout.
 */
class RateLimitBucketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_busy_search_suggestions_do_not_block_checkout(): void
    {
        foreach (range(1, 25) as $i) {
            $this->getJson(route('search.suggest', ['q' => 'panel'.$i]))->assertSuccessful();
        }

        $this->assertNotSame(429, $this->post(route('checkout.store'))->getStatusCode());
    }

    public function test_every_throttled_route_has_its_own_bucket(): void
    {
        $routes = collect(app('router')->getRoutes())->filter(
            fn ($route) => collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && preg_match('/^throttle:\d+,\d+$/', $m))
        )->map(fn ($route) => $route->uri())->values()->all();

        $this->assertSame([], $routes, 'Rute ber-throttle tanpa prefix (berbagi jatah): '.implode(', ', $routes));
    }
}
