<?php

namespace App\Services;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/** PDF surat penawaran harga (dompdf) — tampil inline, nama file = nomor penawaran. */
class QuotationPdf
{
    public function __construct(private readonly SettingService $settings) {}

    public function response(Quotation $quotation): Response
    {
        $quotation->load(['items.product', 'handler', 'user']);
        $number = $quotation->quotation_number ?: $quotation->rfq_number;
        $pdf = Pdf::loadView('admin.quotations.pdf', $this->data($quotation))->setPaper('a4');

        return $pdf->stream('Penawaran-'.preg_replace('/[^A-Za-z0-9-]/', '', $number).'.pdf');
    }

    /** @return array<string, mixed> */
    public function data(Quotation $quotation): array
    {
        $logo = public_path('images/logo.png');

        return [
            'quotation' => $quotation,
            'company' => [
                'brand' => brand(),
                'legal_name' => (string) $this->settings->get('company.legal_name', config('rekasurya.company.legal_name')),
                'address' => (string) $this->settings->get('company.address', config('rekasurya.company.address')),
                'phone' => (string) $this->settings->get('company.phone', config('rekasurya.company.phone')),
                'email' => (string) $this->settings->get('company.email', config('rekasurya.company.email')),
                'npwp' => (string) $this->settings->get('company.npwp', config('rekasurya.company.npwp')),
                'whatsapp' => $this->settings->whatsappNumber(),
            ],
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
            'bankAccount' => trim((string) $this->settings->get('payment.bank_account', '')),
            'ppnPercent' => $this->settings->ppnPercent(),
            'isDraft' => ! $quotation->quotation_number,
            'publicUrl' => route('quotations.show', $quotation->public_token),
        ];
    }
}
