<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function edit(): View
    {
        $settings = [
            'company.legal_name' => $this->settings->get('company.legal_name'),
            'company.npwp' => $this->settings->get('company.npwp'),
            'company.address' => $this->settings->get('company.address'),
            'company.email' => $this->settings->get('company.email'),
            'company.phone' => $this->settings->get('company.phone'),
            'tax.ppn_percent' => $this->settings->get('tax.ppn_percent', 11),
            'tax.enabled' => (bool) $this->settings->get('tax.enabled', true),
            'whatsapp.enabled' => (bool) $this->settings->get('whatsapp.enabled', true),
            'whatsapp.number' => $this->settings->get('whatsapp.number'),
            'whatsapp.greeting' => $this->settings->get('whatsapp.greeting'),
            'whatsapp.admin_notify' => $this->settings->get('whatsapp.admin_notify'),
            'marketing.meta_pixel_id' => $this->settings->get('marketing.meta_pixel_id'),
            'payment.bank_account' => $this->settings->get('payment.bank_account'),
            'payment.qris_image' => $this->settings->get('payment.qris_image'),
            'pickup.address' => $this->settings->get('pickup.address'),
            'pickup.maps_url' => $this->settings->get('pickup.maps_url'),
        ];

        return view('admin.settings', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_legal_name' => ['nullable', 'string', 'max:255'],
            'company_npwp' => ['nullable', 'string', 'max:100'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:100'],
            'tax_ppn_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'tax_enabled' => ['nullable', 'boolean'],
            'whatsapp_enabled' => ['nullable', 'boolean'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'whatsapp_greeting' => ['nullable', 'string', 'max:500'],
            'whatsapp_admin_notify' => ['nullable', 'string', 'max:30'],
            'marketing_meta_pixel_id' => ['nullable', 'string', 'max:32', 'regex:/^\d*$/'],
            'payment_bank_account' => ['nullable', 'string', 'max:500'],
            'payment_qris_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'pickup_address' => ['nullable', 'string', 'max:1000'],
            'pickup_maps_url' => ['nullable', 'url', 'max:500'],
        ]);

        $this->settings->set('company.legal_name', $data['company_legal_name'] ?? '', 'string', 'company');
        $this->settings->set('company.npwp', $data['company_npwp'] ?? '', 'string', 'company');
        $this->settings->set('company.address', $data['company_address'] ?? '', 'string', 'company');
        $this->settings->set('company.email', $data['company_email'] ?? '', 'string', 'company');
        $this->settings->set('company.phone', $data['company_phone'] ?? '', 'string', 'company');

        $this->settings->set('tax.ppn_percent', (int) $data['tax_ppn_percent'], 'integer', 'tax');
        $this->settings->set('tax.enabled', $request->boolean('tax_enabled'), 'boolean', 'tax');

        $this->settings->set('whatsapp.enabled', $request->boolean('whatsapp_enabled'), 'boolean', 'whatsapp');
        $this->settings->set('whatsapp.number', $data['whatsapp_number'] ?? '', 'string', 'whatsapp');
        $this->settings->set('whatsapp.greeting', $data['whatsapp_greeting'] ?? '', 'string', 'whatsapp');
        $this->settings->set('whatsapp.admin_notify', $data['whatsapp_admin_notify'] ?? '', 'string', 'whatsapp');
        $this->settings->set('marketing.meta_pixel_id', $data['marketing_meta_pixel_id'] ?? '', 'string', 'marketing');

        $this->settings->set('payment.bank_account', $data['payment_bank_account'] ?? '', 'string', 'payment');

        $this->settings->set('pickup.address', $data['pickup_address'] ?? '', 'string', 'pickup');
        $this->settings->set('pickup.maps_url', $data['pickup_maps_url'] ?? '', 'string', 'pickup');

        // QRIS image upload (randomised filename on the public disk). Existing image
        // is kept if no new file is uploaded.
        if ($request->hasFile('payment_qris_image')) {
            $path = $request->file('payment_qris_image')->store('settings', 'public');
            $this->settings->set('payment.qris_image', $path, 'string', 'payment');
        }

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
