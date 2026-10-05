<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\WaCampaign\WablasCampaignClient;
use App\Services\WaCampaign\WaCampaignSettings;
use App\Services\WaCampaign\WaContactService;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pengaturan pengiriman WA Campaign + status koneksi Wablas (kredensial tetap di .env). */
class WaCampaignSettingController extends Controller
{
    public function __construct(private readonly WaCampaignSettings $settings, private readonly AuditService $audit) {}

    public function edit(WablasCampaignClient $client, WhatsAppService $wa): View
    {
        return view('admin.wa-campaign.settings', [
            'values' => $this->settings->all(),
            'mock' => $client->isMock(),
            'enabled' => $wa->isEnabled(),
            'hasSecret' => filled(config('services.wablas.secret')),
            'baseUrl' => (string) config('services.wablas.base_url'),
            'hasWebhookToken' => filled(config('services.wablas.webhook_token')),
            'device' => $client->deviceInfo(),
            'webhookUrl' => route('webhook.wablas'),
        ]);
    }

    public function update(Request $request, WaContactService $contacts): RedirectResponse
    {
        $data = $request->validate([
            'window_start' => ['required', 'date_format:H:i'],
            'window_end' => ['required', 'date_format:H:i', 'after:window_start'],
            'timezone' => ['required', 'timezone'],
            'send_days' => ['nullable', 'array'],
            'send_days.*' => ['integer', 'between:1,7'],
            'per_minute' => ['required', 'integer', 'between:1,30'],
            'per_day' => ['required', 'integer', 'between:1,5000'],
            'min_gap_days' => ['required', 'integer', 'between:0,90'],
            'failure_pause_after' => ['required', 'integer', 'between:0,50'],
            'max_attempts' => ['required', 'integer', 'between:1,5'],
            'footer' => ['required', 'string', 'max:200', 'regex:/stop/i'],
            'test_phone' => ['nullable', 'string', 'max:30'],
            'connection_type' => ['required', 'in:qr,cloud'],
        ], [
            'footer.regex' => 'Footer harus memuat kata STOP agar pelanggan tahu cara berhenti.',
        ]);

        $old = $this->settings->all();
        foreach (['window_start', 'window_end', 'timezone', 'per_minute', 'per_day', 'min_gap_days', 'failure_pause_after', 'max_attempts', 'footer', 'connection_type'] as $k) {
            $this->settings->set($k, $data[$k]);
        }
        $this->settings->set('send_days', implode(',', array_map('intval', $data['send_days'] ?? [1, 2, 3, 4, 5, 6])));
        $test = $contacts->normalize($data['test_phone'] ?? null);
        if (filled($data['test_phone'] ?? null) && ! $test) {
            return back()->withInput()->with('error', 'Nomor tes tidak valid.');
        }
        $this->settings->set('test_phone', $test ?? '');
        $this->audit->log('wa_campaign.settings', null, $old, $this->settings->all());

        return back()->with('success', 'Pengaturan WA Campaign disimpan.');
    }
}
