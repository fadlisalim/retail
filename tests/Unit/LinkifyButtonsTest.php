<?php

namespace Tests\Unit;

use Tests\TestCase;

class LinkifyButtonsTest extends TestCase
{
    public function test_bare_url_becomes_click_button(): void
    {
        $html = linkify_buttons('<p>Pilih paket, klik: https://reka.click/catalog/amal.html?beban=1000</p>');

        $this->assertStringContainsString('KLIK DI SINI', $html);
        $this->assertStringContainsString('href="https://reka.click/catalog/amal.html?beban=1000"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener"', $html);
        // The raw URL text should no longer appear as plain text prefixed by a space.
        $this->assertStringNotContainsString('klik: https://reka.click/catalog/amal.html?beban=1000<', $html);
    }

    public function test_existing_anchor_becomes_click_button_without_double_wrapping(): void
    {
        $html = linkify_buttons('<p>Info <a href="https://reka.click/x?a=1&amp;b=2">di sini</a> ya</p>');

        $this->assertSame(1, substr_count($html, 'KLIK DI SINI'));
        $this->assertSame(1, substr_count($html, '<a '));
        $this->assertStringContainsString('href="https://reka.click/x?a=1&amp;b=2"', $html);
    }

    public function test_non_url_content_is_untouched(): void
    {
        $html = '<p>Tidak ada tautan di sini.</p>';

        $this->assertSame($html, linkify_buttons($html));
    }

    public function test_null_and_empty_are_safe(): void
    {
        $this->assertSame('', linkify_buttons(null));
        $this->assertSame('', linkify_buttons(''));
    }

    public function test_unsafe_scheme_is_not_turned_into_button(): void
    {
        $html = linkify_buttons('<p>Bahaya <a href="javascript:alert(1)">klik</a></p>');

        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringNotContainsString('KLIK DI SINI', $html);
    }
}
