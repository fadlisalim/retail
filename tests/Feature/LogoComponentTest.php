<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Logo header/footer ringan: versi 128px, ukuran eksplisit, footer lazy. */
class LogoComponentTest extends TestCase
{
    public function test_logo_kecil_berukuran_eksplisit_dan_footer_lazy(): void
    {
        $header = Blade::render('<x-logo class="h-9 w-9" />');
        $this->assertStringContainsString('images/logo-sm.png', $header);
        $this->assertStringContainsString('width="128" height="128"', $header);
        $this->assertStringNotContainsString('loading="lazy"', $header);

        $this->assertStringContainsString('loading="lazy"', Blade::render('<x-logo :lazy="true" />'));
        $this->assertFileExists(public_path('images/logo-sm.png'));
    }
}
