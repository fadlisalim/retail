<?php

namespace App\Console\Commands;

use App\Enums\CommissionStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hapus pesanan uji/dummy secara BERSIH: stok dikembalikan (reservasi aktif
 * dilepas, penjualan yang sudah commit dibalikkan + sold_count dikoreksi),
 * lalu pesanan dihapus — anak-anaknya (item, invoice, pembayaran, riwayat,
 * komisi afiliasi, dst.) ikut terhapus lewat cascade FK.
 *
 * Pesanan dengan komisi afiliasi yang SUDAH DIBAYARKAN ditolak — menghapusnya
 * merusak pembukuan payout; selesaikan dulu di modul afiliasi.
 */
class PurgeOrder extends Command
{
    protected $signature = 'order:purge {order_numbers* : Nomor pesanan (mis. RS-260909-TAW7K)} {--force : Tanpa konfirmasi}';

    protected $description = 'Hapus pesanan uji beserta seluruh jejaknya, dengan stok dikembalikan';

    public function handle(StockService $stock): int
    {
        foreach ($this->argument('order_numbers') as $number) {
            $order = Order::with('items')->where('order_number', $number)->first();
            if (! $order) {
                $this->error("{$number}: tidak ditemukan.");

                continue;
            }

            if ($order->affiliateCommissions()->where('status', CommissionStatus::Paid->value)->exists()) {
                $this->error("{$number}: punya komisi afiliasi yang SUDAH DIBAYARKAN — tidak boleh dihapus.");

                continue;
            }

            $this->line("{$number} — {$order->customer_name} · status {$order->status->value} · bayar {$order->payment_status->value} · ".rupiah($order->grand_total));
            if (! $this->option('force') && ! $this->confirm('Hapus pesanan ini beserta seluruh jejaknya?')) {
                continue;
            }

            DB::transaction(function () use ($order, $stock, $number) {
                // 1. Reservasi yang masih aktif → kembali ke stok tersedia.
                $stock->releaseForOrder($order);

                // 2. Penjualan yang sudah commit → balikkan stok & sold_count.
                foreach ($order->reservations()->where('status', 'committed')->get() as $res) {
                    $row = WarehouseStock::where('warehouse_id', $res->warehouse_id)
                        ->where('product_id', $res->product_id)
                        ->where('product_variant_id', $res->product_variant_id)
                        ->lockForUpdate()->first();

                    if ($row) {
                        $row->increment('quantity_available', $res->quantity);
                        StockMovement::create([
                            'warehouse_id' => $row->warehouse_id,
                            'product_id' => $res->product_id,
                            'product_variant_id' => $res->product_variant_id,
                            'type' => StockMovementType::Adjustment->value,
                            'quantity' => $res->quantity,
                            'balance_after' => $row->fresh()->quantity_available,
                            'note' => 'Pembalikan pesanan uji '.$number,
                        ]);
                    }

                    Product::where('id', $res->product_id)
                        ->where('sold_count', '>=', $res->quantity)
                        ->decrement('sold_count', $res->quantity);

                    // Sinkronkan cache kolom stok produk/varian.
                    $product = Product::find($res->product_id);
                    $variant = $res->product_variant_id ? ProductVariant::find($res->product_variant_id) : null;
                    if ($product) {
                        $stock->syncCache($product, $variant);
                    }
                }

                // 3. Hapus pesanan PERMANEN (Order pakai SoftDeletes — delete()
                //    biasa hanya menandai, FK cascade tidak jalan). forceDelete
                //    memicu cascadeOnDelete: item, invoice, pembayaran, riwayat,
                //    komisi, reservasi, dsb. ikut lenyap.
                $order->forceDelete();
            });

            $this->info("{$number}: dihapus bersih (stok & sold_count dikembalikan).");
        }

        return self::SUCCESS;
    }
}
