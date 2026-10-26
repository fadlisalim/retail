<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Models\ProductView;
use App\Models\RecentlyViewedProduct;
use App\Models\Wishlist;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Retensi data tamu (dijadwalkan harian): keranjang & wishlist tamu yang kosong,
 * keranjang tamu terbengkalai, riwayat "terakhir dilihat" tamu, dan log view produk
 * lama. Data milik akun pelanggan (user_id terisi) tidak disentuh, kecuali log view.
 * Dihapus bertahap per 1.000 baris supaya tidak mengunci tabel.
 */
class PruneGuestData extends Command
{
    protected $signature = 'guests:prune {--dry-run : Hitung saja, tidak menghapus}';

    protected $description = 'Bersihkan keranjang/wishlist tamu kosong & data tamu lama';

    public function handle(): int
    {
        $tasks = [
            'keranjang tamu kosong (>1 hari)' => Cart::whereNull('user_id')->where('updated_at', '<', now()->subDay())->whereDoesntHave('allItems'),
            'keranjang tamu terbengkalai (>90 hari)' => Cart::whereNull('user_id')->where('updated_at', '<', now()->subDays(90))
                ->whereDoesntHave('allItems', fn ($q) => $q->where('updated_at', '>=', now()->subDays(90))),
            'wishlist tamu kosong (>1 hari)' => Wishlist::whereNull('user_id')->where('updated_at', '<', now()->subDay())->whereDoesntHave('items'),
            'wishlist tamu lama (>180 hari)' => Wishlist::whereNull('user_id')->where('updated_at', '<', now()->subDays(180))
                ->whereDoesntHave('items', fn ($q) => $q->where('created_at', '>=', now()->subDays(180))),
            'terakhir dilihat milik tamu (>30 hari)' => RecentlyViewedProduct::whereNull('user_id')->where('viewed_at', '<', now()->subDays(30)),
            'log view produk (>180 hari)' => ProductView::where('created_at', '<', now()->subDays(180)),
        ];

        foreach ($tasks as $label => $query) {
            $n = $this->option('dry-run') ? $query->count() : $this->deleteInBatches($query);
            $this->line(($this->option('dry-run') ? 'Akan dihapus' : 'Dihapus')." {$n} {$label}");
        }

        return self::SUCCESS;
    }

    private function deleteInBatches(Builder $query): int
    {
        $total = 0;
        do {
            $ids = (clone $query)->orderBy('id')->limit(1000)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $total += $query->getModel()->newQuery()->whereIn('id', $ids)->delete();
        } while ($ids->count() === 1000);

        return $total;
    }
}
