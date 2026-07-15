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

        $addresses = auth()->user()->addresses()->orderByDesc('is_default')->get();

        return view('storefront.checkout', [
            'cart' => $cart,
            'totals' => $totals,
            'addresses' => $addresses,
            'defaultAddress' => $addresses->firstWhere('is_default', true) ?? $addresses->first(),
            'paymentMethods' => $this->payments->available(),
            // Fresh idempotency key prevents a double-clicked "Bayar" from duplicating.
            'idempotencyKey' => (string) Str::uuid(),
            // Province -> cities map from the Indah tariff table so shipping can be quoted.
            'citiesByProvince' => \App\Models\IndahCargoRate::citiesByProvince(),
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

        // Shipping destination comes from a saved address only (validated to the user).
        $address = $request->user()->addresses()->findOrFail($request->integer('address_id'));

        // Rebuild the chosen quote server-side; never trust a price from the form.
        $quote = $this->shipping->findQuote(
            $cart,
            $address->province,
            $request->shipping_provider,
            $request->shipping_service,
            $address->city,
        );

        if (! $quote) {
            return back()->withInput()->with('error', 'Opsi pengiriman tidak valid. Silakan pilih ulang.');
        }

        // Copy the saved address into the order's shipping details.
        $data = $request->validated() + [
            'recipient_name' => $address->recipient_name,
            'recipient_phone' => $address->phone,
            'company_name' => $address->company_name,
            'npwp' => $address->npwp,
            'province' => $address->province,
            'city' => $address->city,
            'district' => $address->district,
            'subdistrict' => $address->subdistrict,
            'postal_code' => $address->postal_code,
            'address_line' => $address->address_line,
            'landmark' => $address->landmark,
        ];

        $order = $this->checkout->place($cart, $data, $quote);

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
