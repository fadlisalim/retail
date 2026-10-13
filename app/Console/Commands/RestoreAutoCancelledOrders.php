<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Pulihkan pesanan yang dibatalkan OTOMATIS oleh orders:expire-unpaid dalam N jam
 * terakhir (mis. backlog yang terlanjur kena saat cron baru diaktifkan). Pesanan
 * yang dibatalkan manual oleh admin tidak disentuh.
 */
class RestoreAutoCancelledOrders extends Command
{
    protected $signature = 'orders:pulihkan {--sejak=24 : Hanya yang dibatalkan otomatis dalam N jam terakhir} {--nomor=* : Batasi ke nomor pesanan tertentu} {--dry-run : Tampilkan saja, tidak mengubah apa pun}';

    protected $description = 'Pulihkan pesanan yang terlanjur dibatalkan otomatis (kembali ke Menunggu Pembayaran, stok dipesan ulang, customer diberi tahu)';

    public function handle(OrderService $orders): int
    {
        $since = now()->subHours(max(1, (int) $this->option('sejak')));
        $numbers = array_filter((array) $this->option('nomor'));
        $dry = (bool) $this->option('dry-run');

        $candidates = Order::where('status', OrderStatus::Cancelled->value)
            ->where('cancelled_at', '>=', $since)
            ->when($numbers, fn ($q) => $q->whereIn('order_number', $numbers))
            ->orderBy('id')->get()
            ->filter(fn (Order $o) => str_starts_with((string) $o->statusHistories()->where('status', OrderStatus::Cancelled->value)->latest('id')->value('internal_note'), OrderService::AUTO_CANCEL_NOTE_PREFIX));

        $ok = 0;
        foreach ($candidates as $order) {
            if ($dry) {
                $this->line("[dry-run] akan dipulihkan: {$order->order_number} — {$order->customer_name} (dibatalkan {$order->cancelled_at}).");
                $ok++;

                continue;
            }
            try {
                $orders->restore($order, null, 'Dipulihkan: pembatalan otomatis dibatalkan oleh admin (batas waktu bayar diperpanjang).');
                $this->info("Dipulihkan: {$order->order_number} — {$order->customer_name}.");
                $ok++;
            } catch (ValidationException $e) {
                $this->error("Gagal {$order->order_number}: ".collect($e->errors())->flatten()->first());
            }
        }

        $this->info(($dry ? '[dry-run] ' : '')."{$ok} dari {$candidates->count()} pesanan auto-batal dipulihkan.");

        return self::SUCCESS;
    }
}
