<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verifikasi Keuangan atas pesanan lunas. Pesanan yang ditandai lunas oleh
 * bukan-Keuangan (jalur lama sebelum dikunci) TIDAK dihitung sebagai
 * pendapatan sampai Keuangan mengonfirmasinya. Backfill: pesanan lunas yang
 * ada dicap terverifikasi bila penanda lunasnya Keuangan / super admin /
 * gateway (tanpa aktor); sisanya dibiarkan kosong = "perlu verifikasi".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('finance_verified_at')->nullable()->after('paid_at');
            $table->foreignId('finance_verified_by')->nullable()->after('finance_verified_at')->constrained('users')->nullOnDelete();
            $table->index(['payment_status', 'finance_verified_at']);
        });

        Order::where('payment_status', 'paid')
            ->with(['statusHistories' => fn ($q) => $q->where('status', OrderStatus::PaymentVerified->value)->orderBy('id')])
            ->chunkById(200, function ($orders) {
                foreach ($orders as $order) {
                    $history = $order->statusHistories->first();
                    $actorId = $history?->changed_by;
                    $actor = $actorId ? User::with('roles.permissions')->find($actorId) : null;

                    $verified = $history && (
                        $actorId === null                                   // gateway / sistem
                        || ($actor && ($actor->isSuperAdmin() || $actor->hasPermission('payment.manage')))
                    );

                    if ($verified) {
                        $order->forceFill([
                            'finance_verified_at' => $history->created_at ?? $order->paid_at ?? now(),
                            'finance_verified_by' => $actorId,
                        ])->save();
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status', 'finance_verified_at']);
            $table->dropConstrainedForeignId('finance_verified_by');
            $table->dropColumn('finance_verified_at');
        });
    }
};
