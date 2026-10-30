<?php

namespace Database\Seeders;

use App\Services\SettingService;
use Illuminate\Database\Seeder;

/**
 * Nomor WA/HP toko dikembalikan ke 0811-2241-107 (keputusan pemilik 30 Okt 2026),
 * membatalkan {@see NomorWaTokoSeeder}. Telepon perusahaan hanya dikembalikan bila
 * isinya masih nomor sementara 0831-8444-7744 (isian admin lain tidak disentuh).
 */
class NomorWaTokoKembaliSeeder extends Seeder
{
    public const WHATSAPP = '628112241107';

    public const PHONE = '0811-2241-107';

    public function run(): void
    {
        $settings = app(SettingService::class);

        $old = $settings->whatsappNumber();
        $settings->set('whatsapp.number', self::WHATSAPP, 'string', 'whatsapp');
        $this->command?->info("Nomor WA toko: {$old} → ".self::WHATSAPP);

        if ((string) $settings->get('company.phone', '') === NomorWaTokoSeeder::PHONE) {
            $settings->set('company.phone', self::PHONE, 'string', 'company');
            $this->command?->info('Telepon perusahaan: '.NomorWaTokoSeeder::PHONE.' → '.self::PHONE);
        }
    }
}
