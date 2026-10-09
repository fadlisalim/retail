<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Services\QuotationPdf;
use App\Services\QuotationService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(private readonly QuotationService $quotations) {}

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

        return view('admin.quotations.show', compact('quotation') + $this->builderData());
    }

    /** Halaman susun penawaran baru oleh sales (dari katalog atau produk/jasa manual). */
    public function create(): View
    {
        return view('admin.quotations.create', $this->builderData());
    }

    public function store(Request $request): RedirectResponse
    {
        $header = $this->validateHeader($request);
        $pricing = $this->validatePricing($request, null); // validasi dulu, baru buat — agar tidak ada draf kosong
        $quotation = $this->quotations->createManual($header, $request->user());
        $this->applyPricing($request, $quotation, $pricing);

        return redirect()->route('admin.quotations.show', $quotation)
            ->with('success', $request->input('action') === 'send' ? 'Penawaran dibuat dan dikirim ke pelanggan.' : 'Penawaran disimpan sebagai draf. Cek PDF-nya, lalu kirim bila sudah pas.');
    }

    /** Ubah data customer / proyek. */
    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->quotations->updateHeader($quotation, $this->validateHeader($request));

        return back()->with('success', 'Data customer diperbarui.');
    }

    public function price(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->applyPricing($request, $quotation, $this->validatePricing($request, $quotation));

        return back()->with('success', $request->input('action') === 'send' ? 'Penawaran disimpan dan dikirim ke pelanggan.' : 'Penawaran disimpan sebagai draf.');
    }

    /** PDF penawaran (tampil di browser; bisa diunduh/dikirim ke customer). */
    public function pdf(Quotation $quotation): Response
    {
        return app(QuotationPdf::class)->response($quotation);
    }

    private function validateHeader(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('is_staff', 0)],
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'project_name' => ['nullable', 'string', 'max:150'],
            'project_location' => ['nullable', 'string', 'max:191'],
            'technical_notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function validatePricing(Request $request, ?Quotation $quotation): array
    {
        return $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => $quotation
                ? ['nullable', 'integer', Rule::exists('quotation_items', 'id')->where('quotation_id', $quotation->id)]
                : ['nullable', 'prohibited'],
            'items.*.name' => ['required', 'string', 'max:191'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_taxable' => ['nullable', 'boolean'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'apply_tax' => ['nullable', 'boolean'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'valid_until' => ['nullable', 'date'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'action' => ['nullable', 'in:draft,send'],
        ], [
            'items.required' => 'Tambahkan minimal satu baris produk/jasa.',
            'items.*.name.required' => 'Nama item wajib diisi.',
            'items.*.unit_price.required' => 'Harga satuan wajib diisi.',
        ]);
    }

    private function applyPricing(Request $request, Quotation $quotation, array $data): void
    {
        $pricedItems = collect($data['items'])->map(fn (array $item) => [
            'id' => isset($item['id']) ? (int) $item['id'] : null,
            'name' => $item['name'],
            'note' => $item['note'] ?? null,
            'quantity' => (int) $item['quantity'],
            'product_id' => $item['product_id'] ?? null,
            'product_variant_id' => $item['product_variant_id'] ?? null,
            'unit_price' => (float) $item['unit_price'],
            'discount' => (float) ($item['discount'] ?? 0),
            'is_taxable' => (bool) ($item['is_taxable'] ?? false),
        ])->all();

        $meta = [
            'discount' => (float) ($data['discount'] ?? 0),
            'shipping_cost' => (float) ($data['shipping_cost'] ?? 0),
            'apply_tax' => $request->boolean('apply_tax', true),
            'payment_terms' => $data['payment_terms'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'admin_note' => $data['admin_note'] ?? null,
        ];

        $this->quotations->price($quotation, $pricedItems, $meta, $request->user(), send: ($data['action'] ?? 'send') === 'send');
    }

    /** Data katalog untuk editor baris (produk + varian aktif, harga katalog). */
    private function builderData(): array
    {
        return [
            'products' => Product::query()->where('status', 'published')
                ->with(['variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
                ->orderBy('name')->get(['id', 'name', 'sku', 'price', 'sale_price', 'product_type']),
            'customers' => User::where('is_staff', false)->orderBy('name')->get(['id', 'name', 'email', 'whatsapp', 'phone']),
            'ppnPercent' => app(SettingService::class)->ppnPercent(),
            'ppnEnabled' => app(SettingService::class)->ppnEnabled(),
        ];
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
