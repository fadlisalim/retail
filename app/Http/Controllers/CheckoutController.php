<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Services\CartCalculator;
use App\Services\CartService;
use App\Services\AffiliateService;
use App\Services\CheckoutService;
use App\Services\NotificationService;
use App\Services\PaymentManager;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CartCalculator $calculator,
        private readonly ShippingService $shipping,
        private readonly CheckoutService $checkout,
        private readonly PaymentManager $payments,
        private readonly AffiliateService $affiliates,
        private readonly NotificationService $notifications,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $cart = $this->cart->current()->load(['items.product', 'items.variant']);
        $buyable = $this->calculator->buyableItems($cart);

        if ($buyable->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Tidak ada produk yang dapat di-checkout.');
        }

        $totals = $this->calculator->calculate($cart);

        return view('storefront.checkout', [
            'cart' => $cart,
            'totals' => $totals,
            'addresses' => auth()->user()?->addresses ?? collect(),
            'paymentMethods' => $this->payments->available(),
            // Fresh idempotency key prevents a double-clicked "Bayar" from duplicating.
            'idempotencyKey' => (string) Str::uuid(),
            // Province -> cities map from the Indah tariff table, so the buyer picks a
            // province and only that province's destination cities are offered (Title-cased
            // for display; the rate lookup upper-cases again).
            'citiesByProvince' => \App\Models\IndahCargoRate::query()
                ->select('province', 'destination_city')
                ->orderBy('province')->orderBy('destination_city')
                ->get()
                ->groupBy('province')
                ->map(fn ($rows) => $rows->pluck('destination_city')
                    ->map(fn ($c) => Str::title(mb_strtolower($c)))->unique()->values()->all())
                ->sortKeys()
                ->all(),
        ]);
    }

    /** AJAX: recompute shipping options for a chosen destination province. */
    public function shippingOptions(Request $request): JsonResponse
    {
        $request->validate([
            'province' => ['required', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        $cart = $this->cart->current()->load(['items.product', 'items.variant']);
        $quotes = $this->shipping->quotesFor($cart, $request->get('province'), $request->get('city'));

        return response()->json(array_map(fn ($q) => $q->toArray() + [
            'rupiah' => rupiah($q->totalShipping()),
        ], $quotes));
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $cart = $this->cart->current()->load(['items.product', 'items.variant']);

        // Rebuild the chosen quote server-side; never trust a price from the form.
        $quote = $this->shipping->findQuote(
            $cart,
            $request->province,
            $request->shipping_provider,
            $request->shipping_service,
            $request->city,
        );

        if (! $quote) {
            return back()->withInput()->with('error', 'Opsi pengiriman tidak valid. Silakan pilih ulang.');
        }

        $order = $this->checkout->place($cart, $request->validated(), $quote);

        // Attribute the sale to a referring affiliate (last-click cookie), if any.
        $this->affiliates->attributeOrder($order);

        // Order confirmation email (in-app for members, email for guests too).
        $title = 'Pesanan diterima';
        $body = "Terima kasih! Pesanan {$order->order_number} sebesar ".rupiah($order->grand_total).' telah kami terima. Silakan selesaikan pembayaran.';
        $url = route('orders.track', $order->public_token);
        if ($order->user) {
            $this->notifications->toUser($order->user, $title, $body, $url, 'order', true, 'Lihat Pesanan');
        } else {
            $this->notifications->toEmail($order->customer_email, $title, $body, $url, 'Lihat Pesanan');
        }

        // Initialise the charge (VA number / manual bank instructions).
        $payment = $order->payments()->latest()->first();
        if ($payment) {
            $this->payments->startCharge($payment);
        }

        return redirect()
            ->route('orders.pay', $order->public_token)
            ->with('success', 'Pesanan '.$order->order_number.' berhasil dibuat.');
    }
}
