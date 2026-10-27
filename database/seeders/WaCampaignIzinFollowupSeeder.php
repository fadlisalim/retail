<?php

namespace Database\Seeders;

use App\Models\WaCampaign;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Pesan lanjutan campaign minta izin (dikirim otomatis setelah penerima membalas YA):
 * gambar promo Seraphim 345Wp bekas proyek + short link produk dari pemilik. Berlaku
 * juga untuk campaign yang sudah berjalan — pesan izin pertama tetap teks saja.
 */
class WaCampaignIzinFollowupSeeder extends Seeder
{
    public const IMAGE = 'wa-campaign/seraphim-345-bekas-proyek.jpg';

    public const LINK = 'https://energi.click/s/nAe6UL';

    public function run(): void
    {
        $campaign = WaCampaign::where('name', WaCampaignIzinSeeder::CAMPAIGN_NAME)->first();
        if (! $campaign) {
            $this->command?->warn('Campaign izin belum ada — jalankan WaCampaignIzinSeeder dulu.');

            return;
        }

        Storage::disk('public')->put(self::IMAGE, (string) file_get_contents(__DIR__.'/assets/wa/seraphim-345-bekas-proyek.jpg'));
        $campaign->forceFill([
            'image_path' => self::IMAGE,
            'link_url' => self::LINK,
            'followup_message' => WaCampaignIzinSeeder::FOLLOWUP,
        ])->save();

        $this->command?->info("Campaign \"{$campaign->name}\": pesan lanjutan (balasan YA) kini berisi gambar promo + ".self::LINK.'.');
    }
}
