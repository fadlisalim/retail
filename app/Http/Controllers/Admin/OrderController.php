<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ManualOrderService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly ManualOrderService $manualOrders,
    ) {}

    /** Form for recording a sale made on Tokopedia/WhatsApp/offline. */
    public function create(): View
    {
        return view('admin.orders.create', [
            // Variants come along: a variable product's stock and price live on
            // the variant, so the admin must pick which one was sold.
            'products' => Product::query()
                ->with(['variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'price', 'sale_price', 'product_type']),
            'channels' => Order::CHANNELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(array_keys(Order::CHANNELS))],
            'external_reference' => ['nullable', 'string', 'max:60'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:191'],
            'create_customer' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.name' => ['nullable', 'string', 'max:191'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            // Blank = use the catalogue price (sale price when on promo).
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_method' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'internal_note' => ['nullable', 'string', 'max:1000'],
            'mark_paid' => ['nullable', 'boolean'],
            'paid_at' => ['nullable', 'date'],
            'send_thanks' => ['nullable', 'boolean'],
            'skip_stock' => ['nullable', 'boolean'],
        ]);

        foreach ($data['items'] as $i => $item) {
            // A line needs either a catalogue product or a free-text name.
            if (empty($item['product_id']) && trim((string) ($item['name'] ?? '')) === '') {
                return back()->withInput()->withErrors(["items.{$i}.name" => 'Pilih produk atau isi nama item.']);
            }
            // A variable product's stock/price sit on the variant, so one must
            // be chosen — and it has to belong to the selected product.
            if (! empty($item['product_id'])) {
                $variants = ProductVariant::where('product_id', $item['product_id'])->where('is_active', true);
                if (empty($item['variant_id'])) {
                    if ($variants->exists()) {
                        return back()->withInput()->withErrors(["items.{$i}.variant_id" => 'Produk ini punya varian — pilih variannya.']);
                    }
                } elseif (! $variants->whereKey($item['variant_id'])->exists()) {
                    return back()->withInput()->withErrors(["items.{$i}.variant_id" => 'Varian tidak cocok dengan produk yang dipilih.']);
                }
            }
            // A free-text line has no catalogue price to fall back on.
            if (empty($item['product_id']) && ($item['unit_price'] ?? '') === '') {
                return back()->withInput()->withErrors(["items.{$i}.unit_price" => 'Harga wajib diisi untuk item di luar katalog.']);
            }
        }

        try {
            $order = $this->manualOrders->create($data, $data['items'], $request->user());
        } catch (\RuntimeException $e) {
            // Almost always "stok tidak mencukupi": the sale is real, so guide
            // the admin instead of failing with a server error.
            return back()->withInput()->withErrors([
                'items' => $e->getMessage().' Perbaiki jumlah/stok, atau centang "Jangan potong stok" bila barang tidak dikelola stoknya di sini.',
            ]);
        }

        $message = 'Pesanan '.$order->order_number.' tercatat.';
        if ($order->payment_status === PaymentStatus::Paid && $request->boolean('send_thanks')) {
            $message .= $this->manualOrders->sendThankYou($order)
                ? ' Ucapan terima kasih terkirim via WhatsApp.'
                : ' (Ucapan terima kasih belum terkirim — cek nomor WA / koneksi Wablas.)';
        }

        return redirect()->route('admin.orders.show', $order)->with('success', $message);
    }

    /**
     * Sunting data penerima pada invoice & kuitansi: nama perusahaan, nama
     * PIC, dan detail penagihan lain. Terkunci begitu pesanan CONFIRMED
     * (Selesai/Dibatalkan/Diretur) — dokumen keuangan transaksi yang sudah
     * tutup tidak boleh berubah lagi.
     */
    public function updateInvoice(Request $request, Order $order): RedirectResponse
    {
        $invoice = $order->invoice;
        abort_unless($invoice, 404);

        if ($order->isInvoiceLocked()) {
            return back()->withErrors(['invoice' => 'Pesanan sudah '.$order->status->label().' — invoice & kuitansi terkunci dan tidak bisa diedit lagi.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'pic' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:500'],
            'npwp' => ['nullable', 'string', 'max:40'],
        ]);

        $invoice->update([
            'customer_snapshot' => array_merge((array) $invoice->customer_snapshot, [
                'name' => $data['name'],
                'company' => $data['company'] ?: null,
                'pic' => $data['pic'] ?: null,
                'phone' => $data['phone'] ?: null,
                'email' => $data['email'] ?: null,
                'address' => $data['address'] ?: null,
                'npwp' => $data['npwp'] ?: null,
            ]),
        ]);

        return back()->with('success', 'Data invoice & kuitansi diperbarui.');
    }

    /** Payment receipt (kuitansi) — print-friendly, paid orders only. */
    public function receipt(Order $order): View
    {
        abort_unless($order->payment_status === PaymentStatus::Paid, 404);

        $order->load(['items', 'invoice', 'shippingAddress']);

        return view('admin.orders.receipt', ['order' => $order]);
    }

    /** Manually (re)send the thank-you WhatsApp for a paid order. */
    public function thanks(Order $order): RedirectResponse
    {
        abort_unless($order->payment_status === PaymentStatus::Paid, 404);

        return back()->with(
            'success',
            $this->manualOrders->sendThankYou($order)
                ? 'Ucapan terima kasih terkirim via WhatsApp.'
                : 'Tidak terkirim — mungkin sudah pernah dikirim, nomor WA kosong, atau Wablas nonaktif.',
        );
    }

    public function index(Request $request): View
    {
        $query = Order::query()->with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($sub) use ($term) {
                $sub->where('order_number', 'like', "%{$term}%")
                    ->orWhere('customer_name', 'like', "%{$term}%")
                    ->orWhere('customer_email', 'like', "%{$term}%");
            });
        }

        $orders = $query->latest()->paginate(20)->withQueryString();

        $statusCounts = Order::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $totalCount = Order::count();

        return view('admin.orders.index', compact('orders', 'statusCounts', 'totalCount'));
    }

    public function show(Order $order): View
    {
        $order->load([
            'items.product', 'shippingAddress', 'statusHistories.changedBy',
            'payments', 'invoice', 'reservations.product', 'user',
        ]);

        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'internal_note' => ['nullable', 'string', 'max:2000'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->orders->changeStatus(
            $order,
            OrderStatus::from($data['status']),
            $request->user(),
            $data['internal_note'] ?? null,
            $data['customer_note'] ?? null,
        );

        return back()->with('success', 'Status pesanan diperbarui.');
    }

    public function confirmShipping(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'shipping_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $this->orders->confirmShippingCost($order, (float) $data['shipping_cost'], $request->user());

        return back()->with('success', 'Ongkir berhasil dikonfirmasi.');
    }

    public function ship(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'tracking_number' => ['required', 'string', 'max:100'],
            'provider' => ['nullable', 'string', 'max:100'],
        ]);

        $this->orders->ship($order, $data['tracking_number'], $data['provider'] ?? null, $request->user());

        return back()->with('success', 'Pesanan ditandai telah dikirim.');
    }

    public function verifyPayment(Request $request, Order $order): RedirectResponse
    {
        $this->orders->markPaid($order, $request->user());

        // Same courtesy as a manual sale: thank the customer on WhatsApp once.
        $thanked = $this->manualOrders->sendThankYou($order->refresh());

        return back()->with('success', 'Pembayaran berhasil diverifikasi.'.($thanked ? ' Ucapan terima kasih terkirim via WhatsApp.' : ''));
    }
}
