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
 * pembeli datang dari afiliator tapi cookie referral tidak terbaca), lalu
 * catat komisinya mengikuti status pesanan: sudah dibayar → komisi ditahan
 * (pending); sudah selesai → komisi disetujui (bisa ditarik). Idempotent —
 * komisi tidak digandakan bila dijalankan ulang.
 */
class AttributeOrderToAffiliate extends Command
{
    protected $signature = 'affiliate:attribute {order_number : Nomor pesanan, mis. ORD-260827-PLG8Y} {code : Kode afiliator, mis. eU5ACZ} {--force : Tanpa konfirmasi; juga memindahkan dari afiliator lain}';

    protected $description = 'Kaitkan pesanan ke afiliator dan catat komisinya sesuai status pesanan';

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
        $this->line('Afiliator: '.$affiliate->user?->name.' (kode '.$affiliate->code.', default fee '.$affiliates->defaultRate().'%).');

        if (! $this->option('force') && ! $this->confirm('Kaitkan pesanan ini ke afiliator tersebut?', true)) {
            $this->line('Dibatalkan.');

            return self::SUCCESS;
        }

        if ($order->affiliate_id && $order->affiliate_id !== $affiliate->id) {
            $order->affiliateCommissions()->delete(); // belum ada yang dibayar (dicek di atas)
        }
        $order->update(['affiliate_id' => $affiliate->id]);

        $paid = in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true);
        $closed = in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Returned], true);

        if ($closed) {
            $this->warn('Pesanan dibatalkan/diretur — dikaitkan, tapi tidak ada komisi.');

            return self::SUCCESS;
        }
        if (! $paid) {
            $this->warn('Pesanan belum dibayar — komisi akan tercatat otomatis saat pembayaran diverifikasi.');

            return self::SUCCESS;
        }

        $affiliates->recordCommissions($order);
        if ($order->status === OrderStatus::Completed) {
            $affiliates->approveCommissions($order);
        }

        $rows = $order->affiliateCommissions()->with('product')->get();
        $this->table(['Produk', 'Dasar', 'Fee %', 'Komisi', 'Status'], $rows->map(fn ($c) => [
            mb_strimwidth($c->product?->name ?? '#'.$c->product_id, 0, 50, '…'),
            rupiah($c->base_amount), (float) $c->rate.'%', rupiah($c->amount), $c->status->value,
        ])->all());
        $this->info('Total komisi '.rupiah($rows->sum('amount')).' — '.($order->status === OrderStatus::Completed
            ? 'DISETUJUI (bisa ditarik afiliator).'
            : 'ditahan (pending) sampai pesanan Selesai.'));

        return self::SUCCESS;
    }
}
