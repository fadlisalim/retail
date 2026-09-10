<?php

namespace App\Console\Commands;

use App\Enums\CommissionStatus;
use App\Models\Order;
use App\Services\AffiliateService;
use Illuminate\Console\Command;

/**
 * Kirim (ulang) notifikasi komisi ke afiliator sebuah pesanan — mis. komisi
 * yang disetujui sebelum fitur notifikasi ada. Isi pesan mengikuti status
 * komisi saat ini: siap ditarik / ditahan sampai Selesai.
 */
class NotifyAffiliateCommission extends Command
{
    protected $signature = 'affiliate:notify {order_number : Nomor pesanan}';

    protected $description = 'Kirim ulang notifikasi komisi (in-app, email, WA) ke afiliator pesanan tersebut';

    public function handle(AffiliateService $affiliates): int
    {
        $order = Order::with('affiliate.user')->where('order_number', $this->argument('order_number'))->first();
        if (! $order) {
            $this->error('Pesanan tidak ditemukan.');

            return self::FAILURE;
        }
        if (! $order->affiliate) {
            $this->error('Pesanan ini tidak terkait afiliator.');

            return self::FAILURE;
        }

        $statuses = $order->affiliateCommissions()->pluck('status');
        if ($statuses->contains(CommissionStatus::AwaitingReview)) {
            $this->warn('Masih ada komisi yang menunggu review super admin — setujui dulu, notifikasi terkirim otomatis setelahnya.');

            return self::FAILURE;
        }

        $kind = $statuses->contains(CommissionStatus::Pending) ? 'recorded' : 'approved';
        if (! $affiliates->notifyCommission($order, $kind)) {
            $this->warn('Tidak ada komisi aktif untuk pesanan ini, atau afiliator tidak punya akun.');

            return self::FAILURE;
        }

        $this->info('Notifikasi "'.($kind === 'approved' ? 'komisi siap ditarik' : 'komisi ditahan').'" dikirim ke '
            .$order->affiliate->user->name.' ('.($order->affiliate->user->waNumber() ?: 'tanpa nomor WA').').');

        return self::SUCCESS;
    }
}
