<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Audit pesanan LUNAS: siapa yang menandai lunas dan apakah dia Keuangan.
 * Sebelum Okt 2026 sales juga bisa menandai lunas (lewat status "Pembayaran
 * Diverifikasi" atau centang "Sudah dibayar" di pesanan manual), jadi angka
 * pendapatan lama bisa memuat pesanan yang belum dikonfirmasi Keuangan.
 * Perintah ini mendaftar semuanya agar Keuangan bisa mencocokkan dengan
 * kuitansi/rekening. Hanya membaca.
 */
class AuditPaidOrders extends Command
{
    protected $signature = 'pendapatan:audit
        {--tahun= : Tahun yang diperiksa (default: tahun ini)}
        {--semua : Tampilkan semua pesanan lunas, bukan hanya yang perlu dicek}';

    protected $description = 'Daftar pesanan lunas beserta siapa yang memverifikasi (Keuangan / super admin / gateway / sales)';

    public function handle(): int
    {
        $year = (int) ($this->option('tahun') ?: now()->format('Y'));
        $start = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $end = $start->endOfYear();

        $orders = Order::with(['statusHistories' => fn ($q) => $q->where('status', OrderStatus::PaymentVerified->value)->orderBy('id'), 'statusHistories.changedBy.roles'])
            ->where('payment_status', 'paid')
            ->where(fn ($q) => $q->whereBetween('paid_at', [$start, $end])
                ->orWhere(fn ($q2) => $q2->whereNull('paid_at')->whereBetween('created_at', [$start, $end])))
            ->orderBy('paid_at')->orderBy('id')
            ->get();

        $rows = [];
        $summary = ['Keuangan' => [0, 0.0], 'Super Admin' => [0, 0.0], 'Gateway/sistem' => [0, 0.0], 'PERLU CEK' => [0, 0.0]];

        foreach ($orders as $order) {
            $history = $order->statusHistories->first();
            $actor = $history?->changedBy;

            if ($history && ! $history->changed_by) {
                $who = 'gateway / sistem';
                $group = 'Gateway/sistem';
            } elseif (! $actor) {
                $who = $history ? 'user terhapus' : 'tidak tercatat';
                $group = 'PERLU CEK';
            } elseif ($actor->isSuperAdmin()) {
                $who = $actor->name.' (super admin)';
                $group = 'Super Admin';
            } elseif ($actor->hasPermission('payment.manage')) {
                $who = $actor->name.' (keuangan)';
                $group = 'Keuangan';
            } else {
                $who = $actor->name.' ('.($actor->roles->pluck('name')->implode(', ') ?: 'tanpa role').')';
                $group = 'PERLU CEK';
            }

            $summary[$group][0]++;
            $summary[$group][1] += (float) $order->grand_total;

            if ($this->option('semua') || $group !== 'Keuangan') {
                $rows[] = [
                    $order->order_number,
                    ($order->paid_at ?? $order->created_at)->format('d/m/Y'),
                    $order->channelLabel(),
                    rupiah($order->grand_total),
                    $who,
                    $group === 'PERLU CEK' ? '⚠ CEK' : ($group === 'Keuangan' ? 'OK' : 'ok'),
                ];
            }
        }

        $this->info("Pesanan lunas {$year}: ".$orders->count().' pesanan, total '.rupiah($orders->sum('grand_total')));
        $this->table(['Verifikator', 'Pesanan', 'Pendapatan'], collect($summary)->map(fn ($v, $k) => [$k, $v[0], rupiah($v[1])])->values()->all());

        if ($rows) {
            $this->newLine();
            $this->line($this->option('semua') ? 'Semua pesanan lunas:' : 'Pesanan yang TIDAK diverifikasi Keuangan (cocokkan dengan kuitansi / mutasi rekening):');
            $this->table(['No. Pesanan', 'Lunas', 'Kanal', 'Total', 'Ditandai lunas oleh', ''], $rows);
            $this->line('Super admin & gateway umumnya sah; baris "⚠ CEK" ditandai lunas oleh sales atau tanpa jejak — bila ternyata belum dibayar, batalkan pesanannya atau hubungi Keuangan.');
        } else {
            $this->info('Semua pesanan lunas tahun ini diverifikasi oleh Keuangan.');
        }

        return self::SUCCESS;
    }
}
