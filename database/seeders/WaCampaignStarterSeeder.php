<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\WaCampaign;
use App\Models\WaCampaignTemplate;
use App\Services\WaCampaign\WaCampaignSettings;
use Illuminate\Database\Seeder;

/**
 * Starter WA Campaign (Okt 2026): pengaturan kirim konservatif (100/hari, 2/menit,
 * 09–17 WIB Sen–Jum, jeda 7 hari), template pesan Seraphim, dan campaign DRAF
 * "Seraphim 345Wp — customer legacy" (produk katalog → foto & link otomatis,
 * penerima = tag legacy). Admin tinggal cek preview, kirim tes, lalu Mulai.
 * Idempotent: template/campaign yang sudah ada tidak dibuat ulang; pengaturan
 * ditimpa sesuai nilai di sini (keputusan pemilik).
 */
class WaCampaignStarterSeeder extends Seeder
{
    public const TEMPLATE_NAME = 'Seraphim 345Wp — promo singkat (legacy)';

    public const CAMPAIGN_NAME = 'Seraphim 345Wp — customer legacy';

    public const MESSAGE = <<<'TXT'
Halo Kak {nama}, ini dari Energi.click (Rekasurya). Kami ada stok terbatas *panel surya Seraphim 345Wp bekas proyek*: mono 72 sel double glass, fungsi normal, garansi toko 3 tahun, *Rp 850.000/panel*. Stok 100 unit, ambil banyak bisa nego — cocok untuk PLTS rumah, pompa air, atau proyek hemat budget.

Detail & foto: {link}

Kalau belum berminat, abaikan saja ya Kak 🙏
TXT;

    public function run(): void
    {
        $settings = app(WaCampaignSettings::class);
        foreach ([
            'per_day' => 100, 'per_minute' => 2, 'window_start' => '09:00', 'window_end' => '17:00',
            'send_days' => '1,2,3,4,5', 'min_gap_days' => 7, 'failure_pause_after' => 5, 'max_attempts' => 3,
            'connection_type' => 'qr', 'footer' => 'Balas STOP untuk berhenti promo.',
        ] as $key => $value) {
            $settings->set($key, $value);
        }

        $product = Product::where('sku', 'SERAPHIM-SRP-345-6MA-DG')->first();

        WaCampaignTemplate::firstOrCreate(['name' => self::TEMPLATE_NAME], ['body' => self::MESSAGE]);

        // Draf yang sudah dibatalkan/selesai tidak dipakai lagi → buat draf baru dengan nama bertanggal.
        $name = self::CAMPAIGN_NAME;
        $stale = WaCampaign::where('name', $name)->whereIn('status', [WaCampaign::CANCELLED, WaCampaign::COMPLETED])->exists();
        if ($stale) {
            $name .= ' ('.now()->format('d/m').')';
        }
        $campaign = WaCampaign::firstOrCreate(['name' => $name], [
            'status' => WaCampaign::DRAFT,
            'message' => self::MESSAGE,
            'product_id' => $product?->id,
            'utm_campaign' => 'seraphim-345-legacy',
            'segment' => ['tags' => ['legacy'], 'sources' => ['ridl']],
        ]);

        $this->command?->info('WA Campaign starter: pengaturan 100/hari · 2/menit · 09–17 Sen–Jum · jeda 7 hari; template "'.self::TEMPLATE_NAME.'"; campaign draf "'.$name.'"'.($campaign->wasRecentlyCreated ? ' dibuat' : ' sudah ada').($product ? '' : ' (produk Seraphim belum ada — pilih produk manual di builder)').'.');
        $this->command?->warn('Langkah: Admin → WA Campaign → buka draf → cek preview → Kirim tes → Simpan & Mulai. Pastikan nomor tes di Pengaturan & webhook Wablas sudah diisi.');
    }
}
