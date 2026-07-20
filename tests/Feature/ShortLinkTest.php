<?php

namespace Tests\Feature;

use App\Models\ShortLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_is_idempotent_per_url(): void
    {
        $a = ShortLink::for('https://energi.click/produk/foo');
        $b = ShortLink::for('https://energi.click/produk/foo');
        $c = ShortLink::for('https://energi.click/produk/foo?ref=ABC');

        $this->assertSame($a->code, $b->code);            // same URL → same code
        $this->assertNotSame($a->code, $c->code);         // ?ref= → different link
        $this->assertSame(6, strlen($a->code));
        $this->assertSame(2, ShortLink::count());
    }

    public function test_resolve_redirects_and_counts_click(): void
    {
        $link = ShortLink::for('https://energi.click/produk/foo?ref=ABC');

        $this->get('/s/'.$link->code)
            ->assertRedirect('https://energi.click/produk/foo?ref=ABC');

        $this->assertSame(1, $link->fresh()->clicks);
    }

    public function test_unknown_code_is_404(): void
    {
        $this->get('/s/nope99')->assertNotFound();
    }
}
