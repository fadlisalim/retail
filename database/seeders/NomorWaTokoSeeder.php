<?php

namespace Database\Seeders;

use App\Services\SettingService;
use Illuminate\Database\Seeder;

/**
 * Nomor HP/WhatsApp CS energi.click diganti ke 0831-8444-7744 (nomor lama diblokir,
 * keputusan pemilik 29 Okt 2026). Dipakai semua tombol "Chat WA", konfirmasi stok, dll.
 * Telepon perusahaan ikut diganti hanya bila isinya nomor HP / placeholder — telepon
 * kantor (fixed line) isian admin tidak disentuh.
 */
class NomorWaTokoSeeder extends Seeder
{
    public const WHATSAPP = '6283184447744';

    public const PHONE = '0831-8444-7744';

    public function run(): void
    {
        $settings = app(SettingService::class);

        $old = $settings->whatsappNumber();
        $settings->set('whatsapp.number', self::WHATSAPP, 'string', 'whatsapp');
        $this->command?->info("Nomor WA toko: {$old} → ".self::WHATSAPP);

        $phone = (string) $settings->get('company.phone', '');
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '' || preg_match('/^(08|628)/', $digits) || in_array($phone, ['021-5000-1234', '021-0000-0000'], true)) {
            $settings->set('company.phone', self::PHONE, 'string', 'company');
            $this->command?->info("Telepon perusahaan: {$phone} → ".self::PHONE);
        } else {
            $this->command?->warn("Telepon perusahaan {$phone} (telepon kantor) tidak diubah.");
        }
    }
}
