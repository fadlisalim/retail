<?php

namespace Tests\Feature;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Beranda: semua banner diambil dalam satu query, tiap posisi tetap tampil benar. */
class HomeBannerQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_loads_all_banner_positions_in_one_query(): void
    {
        foreach (['hero', 'grid', 'video', 'quotation'] as $position) {
            Banner::forceCreate(['title' => "Banner {$position}", 'position' => $position, 'is_active' => true, 'sort_order' => 0]);
        }
        Banner::forceCreate(['title' => 'Banner mati', 'position' => 'hero', 'is_active' => false, 'sort_order' => 1]);

        DB::enableQueryLog();
        $response = $this->get('/');
        $bannerQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "banners"'));

        $response->assertOk();
        $this->assertCount(1, $bannerQueries);
        $this->assertCount(1, $response->viewData('heroBanners'));
        $this->assertCount(1, $response->viewData('gridBanners'));
        $this->assertCount(1, $response->viewData('videoBanners'));
        $this->assertSame('Banner quotation', $response->viewData('quotationBanner')->title);
    }
}
