<?php

namespace App\Services;

use App\Enums\CommissionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Order lifecycle transitions. Each transition writes a status-history row (who,
 * when, notes) and fires the right inventory + notification side effects.
 */
class OrderService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly NotificationService $notifications,
        private readonly AffiliateService $affiliates,
    ) {}

    /** Notify the buyer in-app + by email; guests (no account) get email only. */
    private function notifyCustomer(Order $order, string $title, string $message, string $type): void
    {
        $url = route('orders.track', $order->public_token);

        if ($order->user) {
            $this->notifications->toUser($order->user, $title, $message, $url, $type, true, 'Lihat Pesanan');
        } else {
            $this->notifications->toEmail($order->customer_email, $title, $message, $url, 'Lihat Pesanan');
        }
    }

    /** Status pemenuhan yang terlarang selama pesanan belum dibayar. */
    private const REQUIRES_PAYMENT_FIRST = [
        OrderStatus::Processing,
        OrderStatus::Packing,
        OrderStatus::ReadyForPickup,
        OrderStatus::Shipped,
        OrderStatus::Completed,
    ];

    public function changeStatus(Order $order, OrderStatus $status, ?User $actor = null, ?string $internalNote = null, ?string $customerNote = null): Order
    {
        $unpaid = in_array($order->payment_status, [PaymentStatus::Unpaid, PaymentStatus::AwaitingVerification], true);

        // "Pembayaran Diverifikasi" pada pesanan belum lunas = maksud admin
        // jelas: uang sudah diterima → jalankan pelunasan sungguhan (status
        // bayar, paid_at, stok terjual, komisi afiliator ikut tercatat).
        if ($unpaid && $status === OrderStatus::PaymentVerified) {
            $this->markPaid($order, $actor);

            return $order->refresh(); // markPaid sudah menulis status + riwayatnya
        }

        // Sebaliknya, status pemenuhan (Diproses s/d Selesai) DILARANG selama
        // belum dibayar — barang tidak boleh jalan tanpa uang masuk, dan sistem
        // tidak boleh diam-diam menganggapnya lunas. (Pesanan DP boleh lanjut;
        // pelunasannya diproses lewat panel Pembayaran.)
        if ($unpaid && in_array($status, self::REQUIRES_PAYMENT_FIRST, true)) {
            throw ValidationException::withMessages([
                'status' => 'Pesanan belum dibayar — tidak bisa dipindahkan ke "'.$status->label().'". '
                    .'Verifikasi pembayarannya dulu, atau pilih status "Pembayaran Diverifikasi" bila uangnya sudah diterima.',
            ]);
        }

        return DB::transaction(function () use ($order, $status, $actor, $internalNote, $customerNote) {
            $order->update([
                'status' => $status,
                'completed_at' => $status === OrderStatus::Completed ? now() : $order->completed_at,
                'cancelled_at' => $status === OrderStatus::Cancelled ? now() : $order->cancelled_at,
            ]);

            // Affiliate commissions clear on completion, void on cancel/return.
            if ($status === OrderStatus::Completed) {
                $this->affiliates->approveCommissions($order);
            } elseif (in_array($status, [OrderStatus::Cancelled, OrderStatus::Returned], true)) {
                $this->affiliates->cancelCommissions($order);
            }

            $order->statusHistories()->create([
                'status' => $status->value,
                'changed_by' => $actor?->id,
                'internal_note' => $internalNote,
                'customer_note' => $customerNote,
            ]);

            $this->notifyCustomer($order, 'Status pesanan diperbarui', "Pesanan {$order->order_number} kini: {$status->label()}.", 'order');

            return $order;
        });
    }

    /** Admin confirms the real shipping cost for a "dikonfirmasi" order. */
    public function confirmShippingCost(Order $order, float $shippingCost, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $shippingCost, $actor) {
            $delta = $shippingCost - (float) $order->shipping_cost;
            $order->update([
                'shipping_cost' => $shippingCost,
                'grand_total' => (float) $order->grand_total + $delta,
                'shipping_cost_confirmed' => true,
                'status' => OrderStatus::AwaitingPayment,
            ]);

            $order->statusHistories()->create([
                'status' => OrderStatus::AwaitingPayment->value,
                'changed_by' => $actor?->id,
                'customer_note' => 'Ongkir telah dikonfirmasi: '.rupiah($shippingCost),
            ]);

            $order->payments()->latest()->first()?->update(['amount' => $order->grand_total]);

            $this->notifyCustomer($order, 'Ongkir dikonfirmasi', "Ongkir pesanan {$order->order_number}: ".rupiah($shippingCost).'. Silakan lanjutkan pembayaran.', 'order');

            return $order;
        });
    }

    /** Payment received (full): commit reserved stock into a real sale. */
    /**
     * Koreksi item pesanan (nama, qty, harga; tambah/hapus baris) SETELAH
     * pesanan dibuat. Total, jumlah pembayaran, stok, komisi afiliator, dan
     * dokumen (invoice/kuitansi) ikut berubah — supaya tidak pernah ada dua
     * angka berbeda antara dashboard dan dokumen.
     *
     * @param  list<array{id?:int|string|null,name:string,sku?:string|null,quantity:int|string,unit_price:float|string}>  $rows
     */
    public function correctItems(Order $order, array $rows, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $rows, $actor) {
            $order->load('items');
            $committed = $order->reservations()->where('status', 'committed')->exists();
            $reserved = $order->reservations()->where('status', 'active')->exists();
            $changes = [];
            $kept = [];

            foreach ($rows as $row) {
                $qty = max(1, (int) $row['quantity']);
                $price = round((float) $row['unit_price'], 2);
                $name = Str::limit(trim((string) $row['name']), 191, '');
                $existing = ! empty($row['id']) ? $order->items->firstWhere('id', (int) $row['id']) : null;

                if ($existing) {
                    $delta = $qty - (int) $existing->quantity;
                    if ($delta !== 0 && $existing->product_id) {
                        $this->applyStockDelta($order, $existing, $delta, $committed, $reserved, $actor);
                    }
                    if ($delta !== 0 || round((float) $existing->unit_price, 2) !== $price || $existing->name !== $name) {
                        $changes[] = sprintf('%s: %d × %s → %d × %s', $existing->name, $existing->quantity, rupiah($existing->unit_price), $qty, rupiah($price));
                    }
                    $existing->update([
                        'name' => $name, 'sku' => ($row['sku'] ?? null) ?: $existing->sku,
                        'quantity' => $qty, 'unit_price' => $price, 'line_total' => round($qty * $price, 2),
                    ]);
                    $kept[] = $existing->id;
                } else {
                    $item = $order->items()->create([
                        'product_id' => null, 'product_variant_id' => null, 'sku' => ($row['sku'] ?? null) ?: 'MANUAL', 'name' => $name,
                        'unit_price' => $price, 'original_unit_price' => $price, 'quantity' => $qty,
                        'discount_amount' => 0, 'tax_amount' => 0, 'line_total' => round($qty * $price, 2), 'weight_grams' => 0, 'is_taxable' => false,
                    ]);
                    $kept[] = $item->id;
                    $changes[] = sprintf('+ %s: %d × %s', $name, $qty, rupiah($price));
                }
            }

            foreach ($order->items->whereNotIn('id', $kept) as $removed) {
                if ($removed->product_id) {
                    $this->applyStockDelta($order, $removed, -(int) $removed->quantity, $committed, $reserved, $actor);
                }
                $order->affiliateCommissions()->where('order_item_id', $removed->id)
                    ->whereIn('status', [CommissionStatus::AwaitingReview->value, CommissionStatus::Pending->value, CommissionStatus::Approved->value])
                    ->update(['status' => CommissionStatus::Cancelled->value]);
                $changes[] = sprintf('− %s (%d × %s)', $removed->name, $removed->quantity, rupiah($removed->unit_price));
                $removed->delete();
            }

            $order->load('items');
            $subtotal = round((float) $order->items->sum('line_total'), 2);
            $grand = max(0, round($subtotal - (float) $order->product_discount - (float) $order->coupon_discount
                + (float) $order->shipping_cost + (float) $order->packing_fee + (float) $order->handling_fee + (float) $order->insurance_fee
                + (float) $order->tax_amount, 2));
            $paid = $order->payment_status === PaymentStatus::Paid;

            $order->update([
                'items_subtotal' => $subtotal,
                'grand_total' => $grand,
                'paid_amount' => $paid ? $grand : $order->paid_amount,
                'billable_weight_grams' => (int) $order->items->sum(fn ($i) => (int) $i->weight_grams * (int) $i->quantity),
            ]);
            $order->payments()->latest()->first()?->update($paid ? ['amount' => $grand, 'amount_paid' => $grand] : ['amount' => $grand]);

            // Dokumen mengikuti pesanan: suntingan lama pada baris dokumen dibuang.
            if ($invoice = $order->invoice) {
                $invoice->update([
                    'items_snapshot' => null,
                    'subtotal' => $subtotal,
                    'total' => max(0, round($subtotal - (float) $invoice->discount + (float) $invoice->shipping + (float) $invoice->tax, 2)),
                ]);
            }

            // Komisi afiliator yang belum dibayarkan mengikuti dasar yang baru.
            $order->affiliateCommissions()
                ->whereIn('status', [CommissionStatus::AwaitingReview->value, CommissionStatus::Pending->value, CommissionStatus::Approved->value])
                ->get()
                ->each(function ($commission) use ($order) {
                    $item = $order->items->firstWhere('id', $commission->order_item_id);
                    if ($item) {
                        $commission->update(['base_amount' => $item->line_total, 'amount' => round((float) $item->line_total * (float) $commission->rate / 100, 2)]);
                    }
                });

            if ($changes) {
                $order->statusHistories()->create([
                    'status' => $order->status->value,
                    'changed_by' => $actor?->id,
                    'internal_note' => 'Koreksi item pesanan: '.implode('; ', $changes).'. Total menjadi '.rupiah($grand).'.',
                ]);
            }

            return $order->refresh();
        });
    }

    private function applyStockDelta(Order $order, OrderItem $item, int $delta, bool $committed, bool $reserved, ?User $actor): void
    {
        $product = Product::find($item->product_id);
        if (! $product) {
            return;
        }
        $variant = $item->product_variant_id ? ProductVariant::find($item->product_variant_id) : null;

        if ($committed) {
            $this->stock->adjustCommittedSale($order, $product, $variant, $delta, $actor?->id);
        } elseif ($reserved) {
            $this->stock->adjustReservation($order, $product, $variant, $delta);
        }
        // Tanpa reservasi sama sekali (pesanan manual "jangan potong stok"): stok memang tidak dikelola di sini.
    }

    public function markPaid(Order $order, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $actor) {
            $this->stock->commitForOrder($order);

            $order->update([
                'payment_status' => PaymentStatus::Paid,
                'paid_amount' => $order->grand_total,
                'paid_at' => now(),
                'status' => OrderStatus::PaymentVerified,
            ]);

            $order->payments()->latest()->first()?->update([
                'status' => PaymentStatus::Paid->value,
                'amount_paid' => $order->grand_total,
                'paid_at' => now(),
            ]);

            // Record held affiliate commissions now that the sale is confirmed.
            $this->affiliates->recordCommissions($order);

            $order->statusHistories()->create([
                'status' => OrderStatus::PaymentVerified->value,
                'changed_by' => $actor?->id,
                'customer_note' => 'Pembayaran diterima dan diverifikasi.',
            ]);

            $this->notifyCustomer($order, 'Pembayaran diterima', "Pembayaran pesanan {$order->order_number} telah diverifikasi.", 'payment');

            return $order;
        });
    }

    /** Cancel an order and return any reserved stock to sellable. */
    public function cancel(Order $order, ?User $actor = null, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $actor, $reason) {
            $this->stock->releaseForOrder($order);

            $order->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            $this->affiliates->cancelCommissions($order);

            $order->statusHistories()->create([
                'status' => OrderStatus::Cancelled->value,
                'changed_by' => $actor?->id,
                'internal_note' => $reason,
                'customer_note' => 'Pesanan dibatalkan.',
            ]);

            $this->notifyCustomer($order, 'Pesanan dibatalkan', "Pesanan {$order->order_number} telah dibatalkan.", 'order');

            return $order;
        });
    }

    public function ship(Order $order, string $trackingNumber, ?string $provider = null, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $trackingNumber, $provider, $actor) {
            Shipment::create([
                'order_id' => $order->id,
                'provider' => $provider ?? $order->shipping_service_name,
                'service' => $order->shipping_service_name,
                'tracking_number' => $trackingNumber,
                'status' => 'shipped',
                'billable_weight_grams' => $order->billable_weight_grams,
                'cost' => $order->shipping_cost,
                'shipped_at' => now(),
            ]);

            $order->update(['status' => OrderStatus::Shipped]);
            $order->statusHistories()->create([
                'status' => OrderStatus::Shipped->value,
                'changed_by' => $actor?->id,
                'tracking_number' => $trackingNumber,
                'customer_note' => 'Pesanan dikirim. No. resi: '.$trackingNumber,
            ]);

            $this->notifyCustomer($order, 'Pesanan dikirim', "Pesanan {$order->order_number} sedang dikirim. Resi: {$trackingNumber}.", 'shipping');

            return $order;
        });
    }
}
