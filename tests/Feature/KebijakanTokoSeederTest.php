<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Services\SettingService;
use Database\Seeders\KebijakanTokoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Halaman kebijakan standar marketplace: kontak diisi dari Pengaturan, suntingan admin tidak ditimpa. */
class KebijakanTokoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_pages_are_written_with_live_contact_details_and_admin_edits_are_kept(): void
    {
        $s = app(SettingService::class);
        $s->set('whatsapp.number', '628112241107', 'string', 'whatsapp');
        $s->set('company.email', 'cs@energi.click', 'string', 'company');
        $s->set('company.address', 'Jl. Contoh No. 9, Bandung', 'string', 'company');
        Page::create(['slug' => 'kebijakan-retur', 'title' => 'Kebijakan Retur', 'content' => '<p>Retur dapat diajukan maksimal 7 hari.</p><p>Konten ini dapat diubah melalui panel admin.</p>', 'is_published' => true]);
        Page::create(['slug' => 'tentang-kami', 'title' => 'Tentang', 'content' => '<p>'.str_repeat('Tulisan admin sendiri. ', 20).'</p>', 'is_published' => true]);

        $this->seed(KebijakanTokoSeeder::class);

        $this->assertStringContainsString('video unboxing', Page::where('slug', 'kebijakan-retur')->value('content'));
        $this->assertStringContainsString('Tulisan admin sendiri', Page::where('slug', 'tentang-kami')->value('content'));
        $this->assertSame(9, Page::count());

        $this->get(route('pages.show', 'kebijakan-retur'))->assertOk()
            ->assertSee('wa.me/628112241107', false)->assertSee('08112241107')->assertSee('cs@energi.click')->assertDontSee('{whatsapp}');
        $this->get(route('pages.show', 'panduan-pembayaran'))->assertOk()->assertSee('QRIS')->assertSee('7 × 24 jam')->assertDontSee('Virtual Account');
        $this->get(route('pages.show', 'kontak'))->assertOk()->assertSee('Jl. Contoh No. 9, Bandung');
        $this->get('/')->assertSee(route('pages.show', 'informasi-garansi'));
    }
}
