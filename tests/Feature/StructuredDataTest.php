<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * schema.org JSON-LD must stay valid JSON. Regression guard: Blade compiles
 * directives before echoes and Laravel ships a @context directive, so writing
 * '@context' literally in a view silently leaks raw PHP into the LD block —
 * every JSON-LD payload therefore goes through the schema_ld() helper.
 */
class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    private function ldBlocks(string $html): array
    {
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);

        return $m[1];
    }

    private function assertValidLd(string $html, string $expectedType): void
    {
        $blocks = $this->ldBlocks($html);
        $this->assertNotEmpty($blocks, 'No JSON-LD block rendered.');

        $types = [];
        foreach ($blocks as $block) {
            $data = json_decode(trim($block), true);
            $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'Invalid JSON-LD: '.substr(trim($block), 0, 120));
            $this->assertSame('https://schema.org', $data['@context'] ?? null);
            $types[] = $data['@type'] ?? null;
        }

        $this->assertContains($expectedType, $types);
    }

    public function test_home_renders_valid_organization_ld(): void
    {
        $this->assertValidLd($this->get('/')->assertOk()->getContent(), 'Organization');
    }

    public function test_product_page_renders_valid_product_and_breadcrumb_ld(): void
    {
        $product = Product::factory()->create(['status' => 'published', 'price' => 1500000, 'stock' => 3]);

        $html = $this->get('/produk/'.$product->slug)->assertOk()->getContent();
        $this->assertValidLd($html, 'Product');
        $this->assertValidLd($html, 'BreadcrumbList');
    }

    public function test_faq_page_renders_valid_ld(): void
    {
        $this->assertValidLd($this->get('/faq')->assertOk()->getContent(), 'BreadcrumbList');
    }
}
