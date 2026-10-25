<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\WaCampaign;
use App\Services\WaCampaign\WaCampaignService;
use App\Services\WaCampaign\WaCampaignSettings;
use Illuminate\Database\Seeder;

/**
 * Campaign DRAF "minta izin dulu" untuk customer lama ridlabs (Okt 2026, teks disetujui
 * pemilik): teks saja tanpa link/gambar, ajak balas YA / STOP. Balasan YA otomatis jadi
 * penerima promo dan langsung dikirimi foto + link Seraphim 345Wp. Kuota harian diturunkan
 * ke 40 pesan supaya reputasi nomor naik pelan-pelan. Admin tinggal cek preview, kirim tes, Mulai.
 */
class WaCampaignIzinSeeder extends Seeder
{
    public const CAMPAIGN_NAME = 'Minta izin + Seraphim 345Wp — customer lama';

    public const MESSAGE = <<<'TXT'
Halo Kak {nama}, ini Energi.Click dari Rekasurya (dulu dilayani {sales}) 🙏
Sekarang kami punya toko online panel surya, inverter & baterai.

Kebetulan ada stok terbatas *panel Seraphim 345Wp bekas proyek* (fungsi normal, garansi toko 3 thn) cuma *Rp 850rb/panel*.

Mau kami kirimi foto & detailnya? Balas *YA*.
Kalau tidak berkenan dihubungi lagi, balas *STOP*.
TXT;

    public const FOLLOWUP = <<<'TXT'
Siap Kak {nama}, terima kasih 🙏
Ini foto & detail *{produk}* — {harga}/panel, stok terbatas:
{link}

Mau pesan atau tanya-tanya, balas saja chat ini ya.
TXT;

    public function run(): void
    {
        app(WaCampaignSettings::class)->set('per_day', 40);

        $product = Product::where('sku', 'SERAPHIM-SRP-345-6MA-DG')->first();
        $campaign = WaCampaign::firstOrCreate(['name' => self::CAMPAIGN_NAME], [
            'status' => WaCampaign::DRAFT,
            'message' => self::MESSAGE,
            'followup_message' => self::FOLLOWUP,
            'product_id' => $product?->id,
            'utm_campaign' => 'izin-seraphim-345-legacy',
            'segment' => ['kind' => 'permission', 'tags' => ['legacy'], 'sources' => ['ridl']],
        ]);
        $campaign->forceFill(['audience_count' => app(WaCampaignService::class)->audienceCount($campaign->segment)])->save();

        $this->command?->info("WA Campaign izin: draf \"{$campaign->name}\" ({$campaign->audience_count} kontak belum izin), kuota 40 pesan/hari.");
    }
}
