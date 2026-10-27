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
 * penerima promo dan langsung dikirimi foto + link Seraphim 345Wp. 4 versi redaksi (digilir,
 * isi & harga sama) supaya terlihat versi mana yang paling banyak dibalas. Kuota 40 pesan/hari,
 * rata-rata 2 pesan/menit (jeda acak 20–40 dtk), {sales} = Miftah, sales Energi.Click
 * yang menyapa (bukan sales lama — keputusan pemilik).
 * Admin tinggal cek preview, kirim tes, Mulai. Draf yang belum dikirim ikut diperbarui.
 */
class WaCampaignIzinSeeder extends Seeder
{
    public const CAMPAIGN_NAME = 'Minta izin + Seraphim 345Wp — customer lama';

    public const MESSAGE = <<<'TXT'
Halo Kak {nama}, saya {sales} dari Energi.Click (Rekasurya) 🙏
Kakak dulu pernah terhubung dengan tim Rekasurya — sekarang kami punya toko online panel surya, inverter & baterai.

Kebetulan ada stok terbatas *panel Seraphim 345Wp bekas proyek* (fungsi normal, garansi toko 3 thn) cuma *Rp 850rb/panel*.

Mau saya kirimi foto & detailnya? Balas *YA*.
Kalau tidak berkenan dihubungi lagi, balas *STOP*.
TXT;

    /** Versi 2–4 (versi 1 = MESSAGE). {sales} = sales Energi.Click yang mengirim (bukan sales lama). */
    public const VARIANTS = [
        <<<'TXT'
Permisi Kak {nama} 🙏 Saya {sales} dari Energi.Click – Rekasurya.
Terima kasih dulu pernah terhubung dengan tim Rekasurya; sekarang perlengkapan PLTS (panel surya, inverter, baterai) bisa dipesan online di toko kami.

Lagi ada panel *Seraphim 345Wp bekas proyek* — fungsi normal, garansi toko 3 thn — *Rp 850rb/panel*, stoknya terbatas.

Boleh saya kirim foto & detailnya? Balas *YA*.
Kalau tidak ingin dihubungi lagi, balas *STOP*.
TXT,
        <<<'TXT'
Hai Kak {nama} 👋 {sales} dari Energi.Click (Rekasurya) di sini. Kakak tercatat pernah terhubung dengan tim Rekasurya, jadi saya mau kabari info stok:

panel *Seraphim 345Wp bekas proyek*, kondisi normal, garansi toko 3 tahun, cuma *Rp 850rb/panel* — cocok untuk PLTS rumah, pompa air, atau proyek hemat budget.

Mau dikirimi foto & detailnya? Balas *YA* ya Kak.
Kalau tidak berkenan dihubungi, cukup balas *STOP* 🙏
TXT,
        <<<'TXT'
Halo Kak {nama}, semoga sehat selalu 🙏
Perkenalkan, saya {sales} dari Energi.Click — toko online Rekasurya untuk panel surya, inverter & baterai. Kakak dulu pernah terhubung dengan tim Rekasurya.

Kebetulan ada stok terbatas *panel Seraphim 345Wp bekas proyek* (normal, garansi toko 3 thn) harga *Rp 850rb/panel*.

Kalau mau lihat foto & detailnya, balas *YA*.
Kalau tidak ingin menerima info lagi, balas *STOP*.
TXT,
    ];

    public const FOLLOWUP = <<<'TXT'
Siap Kak {nama}, terima kasih 🙏
Ini detail *panel Seraphim 345Wp bekas proyek* — {harga}/panel:
https://energi.click/s/nAe6UL

Untuk konfirmasi stok & pemesanan, balas saja chat ini ya.
TXT;

    public function run(): void
    {
        $settings = app(WaCampaignSettings::class);
        $settings->set('per_day', 40);
        $settings->set('gap_seconds', 30); // rata-rata 2 pesan/menit, jeda acak 20–40 dtk
        $settings->set('sales_names', '*=Miftah');

        $product = Product::where('sku', 'SERAPHIM-SRP-345-6MA-DG')->first();
        $campaign = WaCampaign::firstOrCreate(['name' => self::CAMPAIGN_NAME], [
            'status' => WaCampaign::DRAFT,
            'message' => self::MESSAGE,
            'followup_message' => self::FOLLOWUP,
            'product_id' => $product?->id,
            'utm_campaign' => 'izin-seraphim-345-legacy',
            'segment' => ['kind' => 'permission', 'tags' => ['legacy'], 'sources' => ['ridl']],
        ]);
        if ($campaign->canEdit()) {
            $campaign->forceFill(['message' => self::MESSAGE, 'message_variants' => self::VARIANTS, 'followup_message' => self::FOLLOWUP])->save();
        }
        $campaign->forceFill(['audience_count' => app(WaCampaignService::class)->audienceCount($campaign->segment)])->save();

        $this->command?->info("WA Campaign izin: draf \"{$campaign->name}\" ({$campaign->audience_count} kontak belum izin), 4 versi pesan, kuota 40/hari, ±2 pesan/menit, sales = Miftah.");
    }
}
