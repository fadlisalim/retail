<?php

namespace Tests\Unit;

use App\Models\Banner;
use PHPUnit\Framework\TestCase;

class BannerLayoutTest extends TestCase
{
    public function test_span_class_maps_width(): void
    {
        $this->assertSame('sm:col-span-6', (new Banner(['span' => 'full']))->spanClass());
        $this->assertSame('sm:col-span-3', (new Banner(['span' => 'half']))->spanClass());
        $this->assertSame('sm:col-span-2', (new Banner(['span' => 'third']))->spanClass());
        $this->assertSame('sm:col-span-2', (new Banner(['span' => null]))->spanClass());
    }

    public function test_youtube_embed_url_from_various_formats(): void
    {
        $cases = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        ];
        foreach ($cases as $input => $expected) {
            $this->assertSame($expected, (new Banner(['button_url' => $input]))->youtubeEmbedUrl());
        }
        $this->assertNull((new Banner(['button_url' => 'https://example.com/notvideo']))->youtubeEmbedUrl());
        $this->assertNull((new Banner(['button_url' => null]))->youtubeEmbedUrl());
    }
}
