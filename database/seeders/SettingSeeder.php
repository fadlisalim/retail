<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['company.legal_name', 'PT Rekasurya Primadaya', 'string', 'company'],
            ['company.brand_name', 'Energi.Click', 'string', 'company'],
            ['company.npwp', '01.234.567.8-901.000', 'string', 'company'],
            ['company.address', 'Jl. Energi Surya No. 1, Jakarta Selatan 12345', 'string', 'company'],
            ['company.email', 'sales@rekasurya.test', 'string', 'company'],
            ['company.phone', '021-5000-1234', 'string', 'company'],
            ['tax.ppn_percent', '11', 'integer', 'tax'],
            // PPN off by default: catalogue prices already include tax.
            ['tax.enabled', '0', 'boolean', 'tax'],
            ['whatsapp.enabled', '1', 'boolean', 'whatsapp'],
            ['whatsapp.number', '628123456789', 'string', 'whatsapp'],
            ['whatsapp.greeting', 'Halo Rekasurya, saya ingin berkonsultasi mengenai produk energi terbarukan.', 'string', 'whatsapp'],
            ['payment.bank_account', 'BCA 123-456-7890 a.n. PT Rekasurya Primadaya', 'string', 'payment'],
            ['shipping.reservation_minutes', '30', 'integer', 'shipping'],
            // Affiliate program defaults.
            ['affiliate.enabled', '1', 'boolean', 'affiliate'],
            ['affiliate.default_rate', '5', 'integer', 'affiliate'],       // percent, used when a product has no rate
            ['affiliate.min_payout', '100000', 'integer', 'affiliate'],    // minimum withdrawal (Rp)
            ['affiliate.cookie_days', '30', 'integer', 'affiliate'],       // last-click attribution window
        ];

        foreach ($settings as [$key, $value, $type, $group]) {
            Setting::updateOrCreate(['key' => $key], [
                'value' => $value, 'type' => $type, 'group' => $group, 'is_public' => true,
            ]);
        }
    }
}
