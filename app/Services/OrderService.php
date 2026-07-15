<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
    ) {
    }

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

    public function changeStatus(Order $order, OrderStatus $status, ?User $actor = null, ?string $internalNote = null, ?string $customerNote = null): Order
    {
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
