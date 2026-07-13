<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
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

        return back()->with('success', 'Pembayaran berhasil diverifikasi.');
    }
}
