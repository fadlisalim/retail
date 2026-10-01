<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Jebakan Blade: `@php(...)` sebaris yang diikuti blok `@php … @endphp` di
 * bawahnya dikompilasi jadi `<?php(` tanpa spasi — bagi PHP itu bukan tag
 * pembuka, jadi seluruh blok (markup, @if, {{ }}) dicetak mentah ke browser
 * tanpa error (kasus kuitansi, Okt 2026). Test ini mengompilasi semua view
 * dan menolak tag pembuka PHP yang tidak diikuti spasi.
 */
class BladeCompileTest extends TestCase
{
    public function test_every_view_compiles_without_broken_php_open_tags(): void
    {
        $broken = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if (! str_ends_with((string) $file, '.blade.php')) {
                continue;
            }
            $compiled = Blade::compileString(file_get_contents((string) $file));
            if (preg_match('/<\?php(?!\s)/', $compiled)) {
                $broken[] = str_replace(resource_path('views').'/', '', (string) $file);
            }
        }

        $this->assertSame([], $broken, 'View dengan tag <?php tanpa spasi (cek @php(...) sebaris sebelum blok @php … @endphp): '.implode(', ', $broken));
    }
}
