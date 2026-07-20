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

    public function test_referral_link_keeps_affiliate_code_visible(): void
    {
        $product = ShortLink::for('https://energi.click/produk/foo');
        $ref = ShortLink::referral('https://energi.click/produk/foo', $product->code, 'K4m2Qp');

        // Code = {productCode}-{affiliateCode}, resolves to the ?ref= URL.
        $this->assertSame($product->code.'-K4m2Qp', $ref->code);
        $this->assertStringEndsWith('-K4m2Qp', $ref->code);

        $this->get('/s/'.$ref->code)
            ->assertRedirect('https://energi.click/produk/foo?ref=K4m2Qp');
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
