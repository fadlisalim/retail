<?php

namespace App\Services;

use App\Enums\AffiliateStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Records a sale that happened OUTSIDE the website (Tokopedia, WhatsApp,
 * showroom) so revenue, stock and customer history stay in one ledger.
 *
 * Marketplaces usually hand over nothing but a name and a phone number, so a
 * customer account can be created from exactly that — an internal placeholder
 * e-mail is generated and the account is left password-less (login-disabled)
 * until the customer registers properly themselves.
 */
class ManualOrderService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly StockService $stock,
        private readonly InvoiceService $invoices,
        private readonly SettingService $settings,
        private readonly WhatsAppService $whatsapp,
    ) {}

    /**
     * @param  array  $data  validated payload from the admin form
     * @param  array  $items  [['product_id'?, 'variant_id'?, 'name'?, 'quantity', 'unit_price']]
     */
    public function create(array $data, array $items, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($data, $items, $actor) {
            $customer = $this->resolveCustomer($data);

            // Kredit afiliator untuk penjualan WA/offline: admin memilih
            // afiliatornya secara manual. Guard self-referral tetap berlaku —
            // afiliator tidak bisa dikreditkan atas pembeliannya sendiri.
            $affiliate = ! empty($data['affiliate_id'])
                ? Affiliate::where('id', $data['affiliate_id'])
                    ->where('status', AffiliateStatus::Active->value)->first()
                : null;
            if ($affiliate && $customer && $affiliate->user_id === $customer->id) {
                $affiliate = null;
            }

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'public_token' => (string) Str::uuid(),
                'channel' => $data['channel'],
                'external_reference' => $data['external_reference'] ?? null,
                'affiliate_id' => $affiliate?->id,
                // Atribusi manual: komisinya menunggu review super admin (bukan otomatis).
                'affiliate_source' => $affiliate ? 'manual' : null,
                'affiliate_attributed_by' => $affiliate ? $actor?->id : null,
                'user_id' => $customer?->id,
                'created_by' => $actor?->id,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? $customer?->email ?? $this->placeholderEmail($data['customer_phone'] ?? null),
                'customer_phone' => $data['customer_phone'] ?? null,
                'status' => OrderStatus::AwaitingPayment,
                'payment_status' => PaymentStatus::Unpaid,
                'shipping_cost_confirmed' => true,
                'shipping_method' => $data['shipping_method'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'customer_note' => $data['customer_note'] ?? null,
                'internal_note' => $data['internal_note'] ?? null,
            ]);

            $subtotal = 0.0;
            $weight = 0;
            foreach ($items as $row) {
                $line = $this->buildItem($row);
                $order->items()->create($line);
                $subtotal += $line['line_total'];
                $weight += $line['weight_grams'] * $line['quantity'];
            }

            $discount = round((float) ($data['discount'] ?? 0), 2);
            $shipping = round((float) ($data['shipping_cost'] ?? 0), 2);
            $tax = round((float) ($data['tax_amount'] ?? 0), 2);
            $grand = max(0, round($subtotal - $discount + $shipping + $tax, 2));

            $order->update([
                'items_subtotal' => $subtotal,
                'product_discount' => $discount,
                'shipping_cost' => $shipping,
                'tax_amount' => $tax,
                'grand_total' => $grand,
                'billable_weight_grams' => $weight,
            ]);

            $order->statusHistories()->create([
                'status' => $order->status->value,
                'changed_by' => $actor?->id,
                'customer_note' => 'Pesanan dicatat manual ('.$order->channelLabel().').',
            ]);

            Payment::create([
                'order_id' => $order->id,
                'method' => $data['payment_method'] ?? 'manual',
                'status' => PaymentStatus::Unpaid->value,
                'amount' => $grand,
            ]);

            // Hold the stock straight away; markPaid() converts it to a sale.
            // A marketplace sale can involve goods this system doesn't track
            // (stock kept elsewhere, or the unit already left the shelf), so
            // the admin can record it without touching stock.
            if (empty($data['skip_stock'])) {
                $this->stock->reserveForOrder($order, 60 * 24 * 7);
            }

            $this->invoices->createForOrder($order->refresh());

            if (! empty($data['mark_paid'])) {
                $this->orders->markPaid($order, $actor);
                $order->refresh();

                if (! empty($data['paid_at'])) {
                    $order->forceFill(['paid_at' => $data['paid_at']])->save();
                }
            }

            return $order;
        });
    }

    /**
     * Thank-you WhatsApp after payment. Sent at most once per order
     * (thanks_sent_at), and only when a phone number is on file.
     */
    public function sendThankYou(Order $order): bool
    {
        if ($order->thanks_sent_at || ! $this->whatsapp->isEnabled()) {
            return false;
        }

        $phone = $this->whatsapp->normalize($order->customer_phone ?: $order->user?->whatsapp ?: $order->user?->phone);
        if (! $phone) {
            return false;
        }

        $firstName = Str::of($order->customer_name)->trim()->explode(' ')->first();
        $text = "Terima kasih Kak {$firstName} 🙏\n"
            .'Pembayaran pesanan '.$order->order_number.' sebesar '.rupiah((float) $order->grand_total)." sudah kami terima.\n\n"
            ."Pesanan Kakak sedang kami proses. Invoice & status pesanan bisa dilihat di:\n"
            .route('orders.track', $order->public_token)."\n\n"
            .'Ada pertanyaan? Balas pesan ini saja ya 😊'."\n— Tim ".brand();

        $sent = $this->whatsapp->send($phone, $text);
        if ($sent) {
            $order->forceFill(['thanks_sent_at' => now()])->save();
        }

        return $sent;
    }

    /** Existing customer by id/phone/email, or a new one from name + WhatsApp. */
    private function resolveCustomer(array $data): ?User
    {
        if (! empty($data['user_id'])) {
            return User::find($data['user_id']);
        }

        $phone = $this->whatsapp->normalize($data['customer_phone'] ?? null);

        if ($phone) {
            $existing = User::where('is_staff', false)
                ->where(fn ($q) => $q->where('whatsapp', $phone)->orWhere('phone', $phone))
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        if (empty($data['create_customer'])) {
            return null;
        }

        return User::create([
            'name' => $data['customer_name'],
            'email' => ($data['customer_email'] ?? null) ?: $this->placeholderEmail($phone),
            // Random secret: the account can't be logged into until the
            // customer sets their own password via "lupa password".
            'password' => Hash::make(Str::random(40)),
            'phone' => $phone,
            'whatsapp' => $phone,
            'is_staff' => false,
            'is_active' => true,
        ]);
    }

    /** Marketplace buyers rarely share an e-mail; keep the column satisfied. */
    private function placeholderEmail(?string $phone): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'energi.click';
        $handle = $phone ? preg_replace('/\D+/', '', $phone) : Str::lower(Str::random(10));

        return 'wa'.$handle.'@pelanggan.'.$host;
    }

    /** One order line, snapshotting a catalogue product or a free-text item. */
    private function buildItem(array $row): array
    {
        $quantity = max(1, (int) ($row['quantity'] ?? 1));
        $product = ! empty($row['product_id']) ? Product::find($row['product_id']) : null;
        $variant = ! empty($row['variant_id']) ? ProductVariant::find($row['variant_id']) : null;

        $unitPrice = isset($row['unit_price']) && $row['unit_price'] !== ''
            ? round((float) $row['unit_price'], 2)
            : (float) ($variant?->price ?? $product?->effectivePrice() ?? 0);

        $name = $product
            ? $product->name.($variant ? ' - '.$variant->name : '')
            : (string) ($row['name'] ?? 'Item');

        return [
            'product_id' => $product?->id,
            'product_variant_id' => $variant?->id,
            'sku' => $variant?->sku ?? $product?->sku ?? 'MANUAL',
            'name' => Str::limit($name, 191, ''),
            'variant_options' => $variant?->option_values,
            'unit_price' => $unitPrice,
            'original_unit_price' => $unitPrice,
            'quantity' => $quantity,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => round($unitPrice * $quantity, 2),
            'weight_grams' => (int) ($variant?->weight_grams ?? $product?->weight_grams ?? 0),
            'is_taxable' => (bool) ($product?->is_taxable ?? false),
        ];
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
    }
}
