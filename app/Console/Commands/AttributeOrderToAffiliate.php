<?php

namespace App\Console\Commands;

use App\Enums\CommissionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Affiliate;
use App\Models\Order;
use App\Services\AffiliateService;
use Illuminate\Console\Command;

/**
 * Kaitkan pesanan yang sudah ada ke seorang afiliator secara manual (mis.
 * pembeli datang dari afiliator tapi cookie referral tidak terbaca). Ini
 * atribusi MANUAL: komisinya masuk sebagai "menunggu review" dan hanya cair
 * setelah disetujui Super Admin di Admin → Afiliasi → Review Komisi.
 * Idempotent — komisi tidak digandakan bila dijalankan ulang.
 */
class AttributeOrderToAffiliate extends Command
{
    protected $signature = 'affiliate:attribute {order_number : Nomor pesanan, mis. ORD-260827-PLG8Y} {code : Kode afiliator, mis. eU5ACZ} {--fee= : Fee % khusus pesanan ini (mis. 5), menggantikan fee produk/default} {--force : Tanpa konfirmasi; juga memindahkan dari afiliator lain}';

    protected $description = 'Kaitkan pesanan ke afiliator (atribusi manual) — komisi menunggu review super admin';

    public function handle(AffiliateService $affiliates): int
    {
        $order = Order::with('items.product', 'affiliate.user')->where('order_number', $this->argument('order_number'))->first();
        if (! $order) {
            $this->error('Pesanan '.$this->argument('order_number').' tidak ditemukan.');

            return self::FAILURE;
        }

        $affiliate = Affiliate::with('user')->where('code', $this->argument('code'))->first();
        if (! $affiliate) {
            $this->error('Afiliator dengan kode '.$this->argument('code').' tidak ditemukan.');

            return self::FAILURE;
        }
        if (! $affiliate->isActive()) {
            $this->error('Afiliator '.$affiliate->user?->name.' berstatus '.$affiliate->status->value.' — hanya afiliator aktif yang bisa menerima komisi.');

            return self::FAILURE;
        }
        if ($order->user_id && $affiliate->user_id === $order->user_id) {
            $this->error('Pesanan ini milik akun afiliator itu sendiri — tidak ada komisi untuk pembelian sendiri.');

            return self::FAILURE;
        }

        $fee = $this->option('fee');
        if ($fee !== null && (! is_numeric($fee) || (float) $fee < 0 || (float) $fee > 100)) {
            $this->error('--fee harus angka persen 0–100.');

            return self::FAILURE;
        }
        $fee = $fee !== null ? (float) $fee : null;

        if ($order->affiliate_id && $order->affiliate_id !== $affiliate->id) {
            $this->warn('Pesanan sudah terkait ke afiliator lain: '.($order->affiliate?->user?->name ?? '#'.$order->affiliate_id).' (kode '.$order->affiliate?->code.').');
            if (! $this->option('force')) {
                $this->error('Gunakan --force untuk memindahkan (komisi afiliator lama yang belum dibayar akan dibatalkan).');

                return self::FAILURE;
            }
            if ($order->affiliateCommissions()->where('status', CommissionStatus::Paid->value)->exists()) {
                $this->error('Komisi afiliator lama sudah DIBAYARKAN — tidak bisa dipindahkan.');

                return self::FAILURE;
            }
        }

        $this->line(sprintf(
            'Pesanan %s — %s — status %s / %s — total %s',
            $order->order_number, $order->customer_name, $order->status->label(), $order->payment_status->label(), rupiah($order->grand_total),
        ));
        $this->line('Afiliator: '.$affiliate->user?->name.' (kode '.$affiliate->code.'), fee '.($fee !== null ? $fee.'% (khusus)' : 'per produk / default '.$affiliates->defaultRate().'%').'.');

        if (! $this->option('force') && ! $this->confirm('Kaitkan pesanan ini ke afiliator tersebut?', true)) {
            $this->line('Dibatalkan.');

            return self::SUCCESS;
        }

        if ($order->affiliate_id && $order->affiliate_id !== $affiliate->id) {
            $order->affiliateCommissions()->delete(); // belum ada yang dibayar (dicek di atas)
        }
        $order->update(['affiliate_id' => $affiliate->id, 'affiliate_source' => 'manual', 'affiliate_attributed_by' => null]);

        $paid = in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true);
        $closed = in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Returned], true);

        if ($closed) {
            $this->warn('Pesanan dibatalkan/diretur — dikaitkan, tapi tidak ada komisi.');

            return self::SUCCESS;
        }
        if (! $paid) {
            $this->warn('Pesanan belum dibayar — komisi akan tercatat (menunggu review) saat pembayaran diverifikasi.'
                .($fee !== null ? ' Catatan: fee khusus hanya berlaku bila dicatat sekarang; jalankan ulang setelah dibayar.' : ''));

            return self::SUCCESS;
        }

        $affiliates->recordCommissions($order, $fee);

        $rows = $order->affiliateCommissions()->with('product')->get();
        $this->table(['Produk', 'Dasar', 'Fee %', 'Komisi', 'Status'], $rows->map(fn ($c) => [
            mb_strimwidth($c->product?->name ?? '#'.$c->product_id, 0, 50, '…'),
            rupiah($c->base_amount), (float) $c->rate.'%', rupiah($c->amount), $c->status->label(),
        ])->all());
        $this->info('Total komisi '.rupiah($rows->sum('amount')).' — MENUNGGU REVIEW. Setujui di Admin → Afiliasi → Review Komisi'
            .($order->status === OrderStatus::Completed ? ' (langsung cair karena pesanan sudah Selesai).' : ' (ditahan sampai pesanan Selesai).'));

        return self::SUCCESS;
    }
}
