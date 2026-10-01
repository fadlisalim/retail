<?php

namespace App\Services;

use App\Enums\CommissionStatus;
use App\Models\AffiliateCommission;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Rekap pendapatan bulanan. Dasar angka = pesanan LUNAS (payment_status =
 * paid), dibukukan pada bulan pembayaran (paid_at; pesanan lama tanpa paid_at
 * memakai created_at) — sama dengan "Pendapatan (Lunas)" di dashboard.
 *
 * Kolom per bulan:
 *  - pendapatan     : grand_total (sudah termasuk ongkir, biaya, PPN, setelah diskon)
 *  - penjualan      : items_subtotal − diskon produk − diskon voucher (nilai barang)
 *  - ongkir_biaya   : ongkir + packing + handling + asuransi
 *  - ppn            : tax_amount
 *  - hpp            : estimasi modal = qty × cost_price produk/varian SAAT INI
 *                     (bukan snapshot; item tanpa modal dihitung 0 dan dilaporkan)
 *  - laba_kotor     : penjualan − hpp
 *  - komisi         : komisi afiliator yang tidak dibatalkan
 *  - per channel    : jumlah pesanan & pendapatan per kanal (website, tokopedia, …)
 * Dihitung di PHP agar sama di MySQL & sqlite.
 */
class MonthlyRevenueReport
{
    /**
     * @return array{year: int, years: list<int>, months: array<int, array<string, mixed>>, total: array<string, mixed>}
     */
    public function year(int $year): array
    {
        $start = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $end = $start->endOfYear();

        $orders = $this->paidOrdersBetween($start, $end);
        $commissions = AffiliateCommission::whereIn('order_id', $orders->pluck('id'))
            ->where('status', '!=', CommissionStatus::Cancelled->value)
            ->get(['order_id', 'amount'])
            ->groupBy('order_id')
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        $cancelled = Order::where('status', 'cancelled')
            ->where(fn ($q) => $q->whereBetween('cancelled_at', [$start, $end])
                ->orWhere(fn ($q2) => $q2->whereNull('cancelled_at')->whereBetween('created_at', [$start, $end])))
            ->get(['id', 'cancelled_at', 'created_at']);
        $unpaid = Order::where('payment_status', 'unpaid')->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $end])->get(['id', 'created_at']);

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = $this->emptyRow($year, $m);
        }

        foreach ($orders as $order) {
            $m = (int) $this->bookingDate($order)->format('n');
            $this->addOrder($months[$m], $order, (float) ($commissions[$order->id] ?? 0));
        }
        foreach ($cancelled as $o) {
            $months[(int) ($o->cancelled_at ?? $o->created_at)->format('n')]['dibatalkan']++;
        }
        foreach ($unpaid as $o) {
            $months[(int) $o->created_at->format('n')]['belum_bayar']++;
        }

        $total = $this->emptyRow($year, 0);
        foreach ($months as $row) {
            $this->finish($row);
            foreach (['pesanan', 'pendapatan', 'penjualan', 'ongkir_biaya', 'ppn', 'hpp', 'laba_kotor', 'komisi', 'item_tanpa_modal', 'dibatalkan', 'belum_bayar'] as $k) {
                $total[$k] += $row[$k];
            }
            foreach ($row['channel'] as $ch => $c) {
                $total['channel'][$ch] = [
                    'pesanan' => ($total['channel'][$ch]['pesanan'] ?? 0) + $c['pesanan'],
                    'pendapatan' => ($total['channel'][$ch]['pendapatan'] ?? 0) + $c['pendapatan'],
                ];
            }
        }
        $this->finish($total);

        // Simpan hasil finish() ke array (foreach by value di atas tidak mengubah $months).
        foreach ($months as $m => $row) {
            $this->finish($months[$m]);
        }

        return ['year' => $year, 'years' => $this->availableYears(), 'months' => $months, 'total' => $total];
    }

    /**
     * Rincian satu bulan: produk terlaris & daftar pesanan lunas.
     *
     * @return array{orders: Collection<int, Order>, products: list<array{name: string, sku: string, qty: int, penjualan: float}>}
     */
    public function month(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $orders = $this->paidOrdersBetween($start, $start->endOfMonth())
            ->sortByDesc(fn (Order $o) => $this->bookingDate($o))->values();

        $products = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $key = $item->product_id.':'.($item->product_variant_id ?? 0).':'.$item->sku;
                $products[$key] ??= ['name' => $item->name, 'sku' => $item->sku, 'qty' => 0, 'penjualan' => 0.0];
                $products[$key]['qty'] += (int) $item->quantity;
                $products[$key]['penjualan'] += (float) $item->line_total;
            }
        }
        usort($products, fn ($a, $b) => $b['penjualan'] <=> $a['penjualan']);

        return ['orders' => $orders, 'products' => array_values($products)];
    }

    /** @return list<int> tahun yang punya pesanan (terbaru dulu), minimal tahun ini */
    public function availableYears(): array
    {
        $first = Order::min('created_at');
        $from = $first ? (int) CarbonImmutable::parse($first)->format('Y') : (int) now()->format('Y');
        $to = (int) now()->format('Y');

        return array_reverse(range(min($from, $to), $to));
    }

    /** Baris CSV (header + 12 bulan + total). @return list<list<string|int|float>> */
    public function csv(int $year): array
    {
        $data = $this->year($year);
        $rows = [['Bulan', 'Pesanan Lunas', 'Pendapatan', 'Penjualan Produk', 'Ongkir & Biaya', 'PPN', 'Estimasi HPP', 'Laba Kotor', 'Komisi Afiliasi', 'Rata-rata/Pesanan', 'Dibatalkan', 'Belum Bayar']];
        foreach ($data['months'] + [13 => $data['total']] as $m => $row) {
            $rows[] = [
                $m === 13 ? 'TOTAL '.$year : $row['label'],
                $row['pesanan'], $row['pendapatan'], $row['penjualan'], $row['ongkir_biaya'], $row['ppn'],
                $row['hpp'], $row['laba_kotor'], $row['komisi'], $row['rata_rata'], $row['dibatalkan'], $row['belum_bayar'],
            ];
        }

        return $rows;
    }

    private function paidOrdersBetween(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Order::with(['items.product:id,cost_price', 'items.variant:id,product_id,cost_price', 'user:id,name'])
            ->where('payment_status', 'paid')
            ->where(fn ($q) => $q->whereBetween('paid_at', [$start, $end])
                ->orWhere(fn ($q2) => $q2->whereNull('paid_at')->whereBetween('created_at', [$start, $end])))
            ->get();
    }

    public function bookingDate(Order $order): CarbonImmutable
    {
        return CarbonImmutable::parse($order->paid_at ?? $order->created_at);
    }

    /** @return array<string, mixed> */
    private function emptyRow(int $year, int $month): array
    {
        return [
            'bulan' => $month,
            'label' => $month ? CarbonImmutable::create($year, $month, 1)->locale('id')->translatedFormat('F Y') : 'Total',
            'pesanan' => 0, 'pendapatan' => 0.0, 'penjualan' => 0.0, 'ongkir_biaya' => 0.0, 'ppn' => 0.0,
            'hpp' => 0.0, 'laba_kotor' => 0.0, 'komisi' => 0.0, 'item_tanpa_modal' => 0,
            'dibatalkan' => 0, 'belum_bayar' => 0, 'rata_rata' => 0.0, 'margin' => 0.0,
            'channel' => [],
        ];
    }

    /** @param array<string, mixed> $row */
    private function addOrder(array &$row, Order $order, float $commission): void
    {
        $penjualan = (float) $order->items_subtotal - (float) $order->product_discount - (float) $order->coupon_discount;
        $hpp = 0.0;
        foreach ($order->items as $item) {
            $cost = $item->variant?->cost_price ?? $item->product?->cost_price;
            if ($cost === null && $item->product_id) {
                $row['item_tanpa_modal']++;
            }
            $hpp += (float) $cost * (int) $item->quantity;
        }

        $row['pesanan']++;
        $row['pendapatan'] += (float) $order->grand_total;
        $row['penjualan'] += $penjualan;
        $row['ongkir_biaya'] += (float) $order->shipping_cost + (float) $order->packing_fee + (float) $order->handling_fee + (float) $order->insurance_fee;
        $row['ppn'] += (float) $order->tax_amount;
        $row['hpp'] += $hpp;
        $row['komisi'] += $commission;

        $ch = $order->channel ?: 'website';
        $row['channel'][$ch] = [
            'pesanan' => ($row['channel'][$ch]['pesanan'] ?? 0) + 1,
            'pendapatan' => ($row['channel'][$ch]['pendapatan'] ?? 0) + (float) $order->grand_total,
        ];
    }

    /** @param array<string, mixed> $row */
    private function finish(array &$row): void
    {
        $row['laba_kotor'] = $row['penjualan'] - $row['hpp'];
        $row['rata_rata'] = $row['pesanan'] ? $row['pendapatan'] / $row['pesanan'] : 0.0;
        $row['margin'] = $row['penjualan'] > 0 ? $row['laba_kotor'] / $row['penjualan'] * 100 : 0.0;
    }
}
