<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Inventory is a ledger, never a bare number.
 *
 *  - warehouse_stocks holds two live buckets per (warehouse, product, variant):
 *    quantity_available (sellable now) and quantity_reserved (held for payment).
 *  - stock_movements is the immutable audit trail of on-hand changes.
 *  - products.stock / variants.stock are a denormalised cache of "sellable",
 *    kept in sync here and used for fast cart validation.
 *
 * Every mutation runs in a transaction with row locking, and available/reserved
 * can never go below zero — that is how negative stock is structurally prevented.
 */
class StockService
{
    public function defaultWarehouse(): Warehouse
    {
        return Warehouse::where('is_default', true)->first()
            ?? Warehouse::firstOrCreate(
                ['code' => 'WH-MAIN'],
                ['name' => 'Gudang Utama', 'is_default' => true, 'is_active' => true],
            );
    }

    private function stockRow(Warehouse $warehouse, Product $product, ?ProductVariant $variant): WarehouseStock
    {
        return WarehouseStock::firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
            ],
            ['quantity_available' => 0, 'quantity_reserved' => 0],
        );
    }

    /**
     * Apply a signed change to on-hand available stock and record a ledger entry.
     * Used for purchases (+), adjustments (±), returns (+), damages (−), etc.
     */
    public function adjust(
        Product $product,
        ?ProductVariant $variant,
        int $delta,
        StockMovementType $type,
        ?Warehouse $warehouse = null,
        ?object $reference = null,
        ?string $note = null,
        ?int $userId = null,
    ): void {
        $warehouse ??= $this->defaultWarehouse();

        DB::transaction(function () use ($product, $variant, $delta, $type, $warehouse, $reference, $note, $userId) {
            $row = WarehouseStock::where('id', $this->stockRow($warehouse, $product, $variant)->id)
                ->lockForUpdate()->first();

            $newAvailable = $row->quantity_available + $delta;
            if ($newAvailable < 0) {
                throw new RuntimeException('Stok tidak boleh menjadi negatif.');
            }

            $row->update(['quantity_available' => $newAvailable]);

            StockMovement::create([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'type' => $type->value,
                'quantity' => $delta,
                'balance_after' => $newAvailable,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference->id ?? null,
                'note' => $note,
                'user_id' => $userId,
            ]);

            $this->syncCache($product, $variant);
        });
    }

    /**
     * Move sellable stock into the reserved bucket for an order entering payment.
     * Creates time-boxed reservations that a scheduler later expires.
     */
    public function reserveForOrder(Order $order, int $minutes = 30): void
    {
        $warehouse = $this->defaultWarehouse();

        DB::transaction(function () use ($order, $warehouse, $minutes) {
            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }
                $product = Product::find($item->product_id);
                $variant = $item->product_variant_id ? ProductVariant::find($item->product_variant_id) : null;

                $row = WarehouseStock::where('id', $this->stockRow($warehouse, $product, $variant)->id)
                    ->lockForUpdate()->first();

                if ($row->quantity_available < $item->quantity) {
                    throw new RuntimeException("Stok tidak mencukupi untuk {$item->name}.");
                }

                $row->update([
                    'quantity_available' => $row->quantity_available - $item->quantity,
                    'quantity_reserved' => $row->quantity_reserved + $item->quantity,
                ]);

                StockReservation::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => $item->quantity,
                    'status' => 'active',
                    'expires_at' => now()->addMinutes($minutes),
                ]);

                $this->syncCache($product, $variant);
            }
        });
    }

    /** Payment confirmed: convert reservations into a real outflow (sale). */
    public function commitForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->reservations()->where('status', 'active')->lockForUpdate()->get() as $res) {
                $product = Product::find($res->product_id);
                $variant = $res->product_variant_id ? ProductVariant::find($res->product_variant_id) : null;
                $row = WarehouseStock::where('id', $this->stockRow($res->warehouse, $product, $variant)->id)
                    ->lockForUpdate()->first();

                $row->update(['quantity_reserved' => max(0, $row->quantity_reserved - $res->quantity)]);
                $res->update(['status' => 'committed']);

                StockMovement::create([
                    'warehouse_id' => $row->warehouse_id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'type' => StockMovementType::Sale->value,
                    'quantity' => -$res->quantity,
                    'balance_after' => $row->quantity_available,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                ]);

                $product->increment('sold_count', $res->quantity);
                $this->syncCache($product, $variant);
            }
        });
    }

    /** Order cancelled/expired before payment: return reserved stock to sellable. */
    public function releaseForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->reservations()->where('status', 'active')->lockForUpdate()->get() as $res) {
                $this->releaseReservation($res);
            }
        });
    }

    public function expireStaleReservations(): int
    {
        $count = 0;
        StockReservation::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($reservations) use (&$count) {
                foreach ($reservations as $res) {
                    DB::transaction(fn () => $this->releaseReservation($res, 'expired'));
                    $count++;
                }
            });

        return $count;
    }

    private function releaseReservation(StockReservation $res, string $status = 'released'): void
    {
        $product = Product::find($res->product_id);
        $variant = $res->product_variant_id ? ProductVariant::find($res->product_variant_id) : null;
        $row = WarehouseStock::where('id', $this->stockRow($res->warehouse, $product, $variant)->id)
            ->lockForUpdate()->first();

        $row->update([
            'quantity_available' => $row->quantity_available + $res->quantity,
            'quantity_reserved' => max(0, $row->quantity_reserved - $res->quantity),
        ]);
        $res->update(['status' => $status]);

        $this->syncCache($product, $variant);
    }

    /** Recompute the denormalised sellable cache from the warehouse buckets. */
    public function syncCache(Product $product, ?ProductVariant $variant): void
    {
        if ($variant) {
            $variant->update([
                'stock' => (int) WarehouseStock::where('product_variant_id', $variant->id)->sum('quantity_available'),
            ]);
        }

        $product->update([
            'stock' => (int) WarehouseStock::where('product_id', $product->id)->sum('quantity_available'),
        ]);
    }
}
