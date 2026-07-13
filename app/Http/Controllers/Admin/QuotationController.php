<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(private readonly QuotationService $quotations)
    {
    }

    public function index(Request $request): View
    {
        $query = Quotation::query()->with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($sub) use ($term) {
                $sub->where('rfq_number', 'like', "%{$term}%")
                    ->orWhere('quotation_number', 'like', "%{$term}%")
                    ->orWhere('company_name', 'like', "%{$term}%")
                    ->orWhere('contact_name', 'like', "%{$term}%");
            });
        }

        $quotations = $query->latest()->paginate(20)->withQueryString();

        $statusCounts = Quotation::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.quotations.index', compact('quotations', 'statusCounts'));
    }

    public function show(Quotation $quotation): View
    {
        $quotation->load([
            'items.product', 'attachments', 'revisions.creator', 'user', 'handler',
        ]);

        return view('admin.quotations.show', compact('quotation'));
    }

    public function price(Request $request, Quotation $quotation): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => [
                'required', 'integer',
                Rule::exists('quotation_items', 'id')->where('quotation_id', $quotation->id),
            ],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_taxable' => ['nullable', 'boolean'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'valid_until' => ['nullable', 'date'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $pricedItems = collect($data['items'])->map(fn (array $item) => [
            'id' => (int) $item['id'],
            'unit_price' => (float) $item['unit_price'],
            'discount' => (float) ($item['discount'] ?? 0),
            'is_taxable' => (bool) ($item['is_taxable'] ?? false),
        ])->all();

        $meta = [
            'discount' => (float) ($data['discount'] ?? 0),
            'shipping_cost' => (float) ($data['shipping_cost'] ?? 0),
            'payment_terms' => $data['payment_terms'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'admin_note' => $data['admin_note'] ?? null,
        ];

        $this->quotations->price($quotation, $pricedItems, $meta, $request->user());

        return back()->with('success', 'Penawaran berhasil disimpan dan dikirim ke pelanggan.');
    }

    public function status(Request $request, Quotation $quotation): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(QuotationStatus::class)],
        ]);

        $quotation->update(['status' => QuotationStatus::from($data['status'])]);

        return back()->with('success', 'Status penawaran diperbarui.');
    }

    public function convert(Request $request, Quotation $quotation): RedirectResponse
    {
        $order = $this->quotations->convertToOrder($quotation, $request->user());

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Penawaran berhasil dijadikan pesanan.');
    }
}
