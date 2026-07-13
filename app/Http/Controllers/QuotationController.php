<?php

namespace App\Http\Controllers;

use App\Enums\QuotationStatus;
use App\Models\Product;
use App\Models\Quotation;
use App\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(private readonly QuotationService $quotations)
    {
    }

    public function create(Request $request): View
    {
        $prefill = null;
        if ($slug = $request->get('produk')) {
            $prefill = Product::where('slug', $slug)->first();
        }

        return view('storefront.quotation-create', ['prefill' => $prefill]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'project_name' => ['nullable', 'string', 'max:191'],
            'project_location' => ['nullable', 'string', 'max:191'],
            'procurement_target' => ['nullable', 'date'],
            'needs_installation' => ['nullable', 'boolean'],
            'technical_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:191'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,xlsx,xls,doc,docx,jpg,png', 'max:5120'],
        ]);

        $quotation = $this->quotations->createRfq($data, $data['items'], auth()->id());

        // BOQ / document upload with a randomised filename (no executables).
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('quotations', 'public');
            $quotation->attachments()->create([
                'type' => 'customer',
                'title' => $request->file('attachment')->getClientOriginalName(),
                'path' => $path,
            ]);
        }

        return redirect()
            ->route('quotations.show', $quotation->public_token)
            ->with('success', 'Permintaan penawaran '.$quotation->rfq_number.' berhasil dikirim.');
    }

    public function show(Quotation $quotation): View
    {
        $quotation->load(['items.product', 'attachments', 'revisions']);

        return view('storefront.quotation-show', ['quotation' => $quotation]);
    }

    public function approve(Quotation $quotation): RedirectResponse
    {
        abort_unless(in_array($quotation->status, [QuotationStatus::QuoteSent, QuotationStatus::Revised], true), 403);

        $quotation->update(['status' => QuotationStatus::Approved]);

        return back()->with('success', 'Penawaran disetujui. Tim kami akan menindaklanjuti pesanan Anda.');
    }

    public function reject(Quotation $quotation): RedirectResponse
    {
        $quotation->update(['status' => QuotationStatus::Rejected]);

        return back()->with('success', 'Penawaran ditolak.');
    }
}
