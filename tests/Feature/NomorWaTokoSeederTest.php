<?php

namespace Tests\Feature;

use App\Services\SettingService;
use Database\Seeders\NomorWaTokoKembaliSeeder;
use Database\Seeders\NomorWaTokoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Nomor WA toko diganti; telepon kantor (fixed line) isian admin tetap. */
class NomorWaTokoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_replaces_whatsapp_and_mobile_company_phone(): void
    {
        $settings = app(SettingService::class);
        $settings->set('whatsapp.number', '628111111111', 'string', 'whatsapp');
        $settings->set('company.phone', '0811-1111-111', 'string', 'company');

        $this->seed(NomorWaTokoSeeder::class);

        $this->assertSame('6283184447744', $settings->whatsappNumber());
        $this->assertSame('0831-8444-7744', $settings->get('company.phone'));
        $this->assertStringContainsString('wa.me/6283184447744', whatsapp_link('Halo'));
    }

    public function test_revert_restores_old_number(): void
    {
        $settings = app(SettingService::class);
        $settings->set('company.phone', '0811-1111-111', 'string', 'company');

        $this->seed(NomorWaTokoSeeder::class);
        $this->seed(NomorWaTokoKembaliSeeder::class);

        $this->assertSame('628112241107', $settings->whatsappNumber());
        $this->assertSame('0811-2241-107', $settings->get('company.phone'));
        $this->assertStringContainsString('wa.me/628112241107', whatsapp_link('Halo'));
    }

    public function test_keeps_office_landline(): void
    {
        $settings = app(SettingService::class);
        $settings->set('company.phone', '022-1234-5678', 'string', 'company');

        $this->seed(NomorWaTokoSeeder::class);

        $this->assertSame('6283184447744', $settings->whatsappNumber());
        $this->assertSame('022-1234-5678', $settings->get('company.phone'));
    }
}
