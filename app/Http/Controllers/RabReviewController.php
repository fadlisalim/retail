<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\QuotationRabReview;
use App\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Halaman review RAB untuk tim internal (engineer, purchasing, dll.) lewat link
 * ber-kunci — tanpa login. Memuat modal & margin, jadi link ini RAHASIA: hanya
 * dibagikan ke tim, dan bisa dicabut (buat link baru) dari halaman penawaran admin.
 */
class RabReviewController extends Controller
{
    public function __construct(private readonly QuotationService $quotations) {}

    public function show(string $token): View
    {
        $quotation = $this->find($token);

        return view('rab-review', [
            'quotation' => $quotation,
            'rab' => $quotation->rab(),
            'review' => $this->quotations->rabReview($quotation),
            'minMargin' => $this->quotations->minMarginPercent(),
            'reviews' => $quotation->rabReviews,
            'currentSignature' => $this->quotations->rabSignature($quotation),
            'divisions' => QuotationRabReview::DIVISIONS,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $quotation = $this->find($token);
        $data = $request->validate([
            'reviewer_name' => ['required', 'string', 'min:2', 'max:120'],
            'division' => ['required', Rule::in(array_keys(QuotationRabReview::DIVISIONS))],
            'decision' => ['required', Rule::in([QuotationRabReview::APPROVED, QuotationRabReview::REVISION])],
            'note' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->input('decision') === QuotationRabReview::REVISION)],
        ], [
            'reviewer_name.required' => 'Tulis nama Anda.',
            'note.required' => 'Tulis apa yang perlu direvisi.',
        ]);

        // Nama tersimpan di browser ini untuk review berikutnya.
        $request->session()->put('rab_reviewer', ['name' => $data['reviewer_name'], 'division' => $data['division']]);

        $this->quotations->reviewViaLink($quotation, $data, $request->ip(), $request->userAgent());

        return redirect()->route('rab.show', $token)->with('success', $data['decision'] === QuotationRabReview::APPROVED
            ? 'Terima kasih, RAB disetujui atas nama '.$data['reviewer_name'].'. Sales sudah bisa mengirim penawaran.'
            : 'Catatan revisi terkirim ke sales.');
    }

    private function find(string $token): Quotation
    {
        abort_unless(strlen($token) >= 32, 404);

        return Quotation::where('rab_token', $token)->with(['items', 'handler', 'rabApprover', 'rabReviews'])->firstOrFail();
    }
}
