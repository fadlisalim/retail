<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [];
        $urls[] = ['loc' => route('home'), 'priority' => '1.0'];
        $urls[] = ['loc' => route('products.index'), 'priority' => '0.9'];

        foreach (Category::active()->get() as $c) {
            $urls[] = ['loc' => route('categories.show', $c->slug), 'priority' => '0.7', 'lastmod' => $c->updated_at?->toAtomString()];
        }
        foreach (Product::published()->get(['slug', 'updated_at']) as $p) {
            $urls[] = ['loc' => route('products.show', $p->slug), 'priority' => '0.8', 'lastmod' => $p->updated_at?->toAtomString()];
        }
        foreach (Article::published()->get(['slug', 'updated_at']) as $a) {
            $urls[] = ['loc' => route('articles.show', $a->slug), 'priority' => '0.5', 'lastmod' => $a->updated_at?->toAtomString()];
        }

        return response()
            ->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /keranjang',
            'Disallow: /checkout',
            'Disallow: /akun',
            'Disallow: /admin',
            'Disallow: /pencarian',
            'Allow: /',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }
}
