<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

/**
 * Auto-cancels orders that are still awaiting payment past the payment window
 * (default 24h). Cancelling releases reserved stock and voids commissions via
 * OrderService::cancel — so abandoned unpaid orders don't tie up inventory.
 *
 * Orders whose payment is being verified (proof uploaded) or partially paid are
 * left alone for an admin to handle. Runs on the scheduler (see routes/console.php).
 */
class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid {--dry-run : List what would be cancelled without changing anything}';

    protected $description = 'Batalkan otomatis pesanan yang belum dibayar melewati batas waktu';

    public function handle(OrderService $orders): int
    {
        $hours = (int) config('rekasurya.orders.payment_window_hours', 24);
        if ($hours <= 0) {
            $this->info('Auto-cancel dinonaktifkan (payment_window_hours = 0).');

            return self::SUCCESS;
        }

        $cutoff = now()->subHours($hours);
        $dry = (bool) $this->option('dry-run');

        // Only genuinely-unpaid orders still awaiting payment. Skip ones under
        // verification / partially paid — those need a human.
        $candidates = Order::where('status', OrderStatus::AwaitingPayment->value)
            ->whereIn('payment_status', [
                PaymentStatus::Unpaid->value,
                PaymentStatus::Failed->value,
                PaymentStatus::Expired->value,
            ])
            ->get();

        $expired = 0;
        foreach ($candidates as $order) {
            // Count from when the order entered "awaiting payment" (fallback: created_at).
            $since = optional(
                $order->statusHistories()
                    ->where('status', OrderStatus::AwaitingPayment->value)
                    ->latest('id')->first()
            )->created_at ?? $order->created_at;

            if ($since->greaterThan($cutoff)) {
                continue; // still within the payment window
            }

            if ($dry) {
                $this->line("[dry-run] akan dibatalkan: {$order->order_number} (menunggu sejak {$since}).");
                $expired++;

                continue;
            }

            $orders->cancel($order, null, "Otomatis dibatalkan: pembayaran tidak diterima dalam {$hours} jam.");
            $expired++;
        }

        $this->info(($dry ? '[dry-run] ' : '')."{$expired} pesanan kedaluwarsa diproses (batas {$hours} jam).");

        return self::SUCCESS;
    }
}
