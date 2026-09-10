<?php

namespace App\Services;

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Referral attribution + commission lifecycle.
 *
 * Attribution is FIRST-click via a cookie set when someone visits with ?ref=CODE
 * (an existing cookie for an active affiliate is never overwritten by another code).
 * Commissions are recorded (held) when an attributed order is paid, cleared when
 * the order completes, and voided if it is cancelled/returned.
 */
class AffiliateService
{
    public const COOKIE = 'ref';

    public function __construct(private readonly SettingService $settings) {}

    public function windowDays(): int
    {
        return (int) $this->settings->get('affiliate.cookie_days', 30);
    }

    public function defaultRate(): float
    {
        return (float) $this->settings->get('affiliate.default_rate', 2.5);
    }

    public function minPayout(): float
    {
        return (float) $this->settings->get('affiliate.min_payout', 100000);
    }

    /** Commission percentage that applies to a product (per-product override, else default). */
    public function rateForProduct(?Product $product): float
    {
        $rate = $product?->affiliate_rate;

        return $rate !== null ? (float) $rate : $this->defaultRate();
    }

    /**
     * Record a click and drop the attribution cookie if the code maps to an
     * active affiliate.
     *
     * Atribusi KLIK PERTAMA: cookie yang sudah ada dan masih menunjuk
     * afiliator aktif TIDAK ditimpa kode lain. Ini melindungi afiliator yang
     * pertama memperkenalkan — tanpa ini, pembeli bisa mendaftar jadi
     * afiliator lalu mengeklik link sendiri untuk merebut (dan karena
     * self-referral diblokir, akhirnya menghanguskan) komisi si pengenal
     * awal. Klik dengan kode yang SAMA tetap memperbarui masa berlaku, dan
     * cookie milik afiliator yang sudah nonaktif boleh digantikan.
     */
    public function trackClick(string $code, Request $request): bool
    {
        $affiliate = Affiliate::where('code', $code)->where('status', AffiliateStatus::Active->value)->first();
        if (! $affiliate) {
            return false;
        }

        $existing = (string) $request->cookie(self::COOKIE);
        if ($existing !== '' && $existing !== $affiliate->code
            && Affiliate::where('code', $existing)->where('status', AffiliateStatus::Active->value)->exists()) {
            // Pengenal pertama masih berhak — klik ini dicatat untuk statistik,
            // tetapi atribusinya tidak berpindah.
            $affiliate->clicks()->create([
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'landing_url' => Str::limit($request->fullUrl(), 1000, ''),
                'referrer' => Str::limit((string) $request->headers->get('referer'), 1000, ''),
            ]);

            return false;
        }

        Cookie::queue(Cookie::make(self::COOKIE, $affiliate->code, $this->windowDays() * 24 * 60));

        $affiliate->clicks()->create([
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'landing_url' => Str::limit($request->fullUrl(), 1000, ''),
            'referrer' => Str::limit((string) $request->headers->get('referer'), 1000, ''),
        ]);

        return true;
    }

    /**
     * Attach the cookie's affiliate to a freshly created order.
     * Skips self-purchases (an affiliate buying through their own link).
     */
    public function attributeOrder(Order $order): void
    {
        if ($order->affiliate_id) {
            return;
        }

        $code = request()->cookie(self::COOKIE);
        if (! $code) {
            return;
        }

        $affiliate = Affiliate::where('code', $code)->where('status', AffiliateStatus::Active->value)->first();
        if (! $affiliate) {
            return;
        }

        if ($order->user_id && $affiliate->user_id === $order->user_id) {
            return; // no self-referral
        }

        $order->update(['affiliate_id' => $affiliate->id]);
    }

    /**
     * On payment: create held commission lines for each order item. Idempotent.
     * Atribusi MANUAL (admin memilih afiliator sendiri) masuk sebagai "menunggu
     * review" — super admin yang meloloskannya, bukan otomatis.
     *
     * @param  float|null  $rateOverride  fee % khusus (mis. kesepakatan per pesanan) menggantikan fee produk/default
     */
    public function recordCommissions(Order $order, ?float $rateOverride = null): void
    {
        if (! $order->affiliate_id) {
            return;
        }

        $order->loadMissing('items.product');
        $manual = $order->affiliate_source === 'manual';

        foreach ($order->items as $item) {
            $rate = $rateOverride ?? $this->rateForProduct($item->product);
            $base = (float) $item->line_total;
            $amount = round($base * $rate / 100, 2);

            if ($rate <= 0 || $amount <= 0) {
                continue;
            }

            AffiliateCommission::firstOrCreate(
                ['order_id' => $order->id, 'order_item_id' => $item->id],
                [
                    'affiliate_id' => $order->affiliate_id,
                    'product_id' => $item->product_id,
                    'base_amount' => $base,
                    'rate' => $rate,
                    'amount' => $amount,
                    'status' => $manual ? CommissionStatus::AwaitingReview : CommissionStatus::Pending,
                    'attributed_by' => $manual ? $order->affiliate_attributed_by : null,
                ]
            );
        }
    }

    /** Super admin meloloskan komisi atribusi manual: langsung cair bila pesanan sudah Selesai, selain itu ditahan dulu. */
    public function reviewApprove(AffiliateCommission $commission, User $reviewer): void
    {
        $commission->loadMissing('order');
        $commission->update([
            'status' => $commission->order?->status === OrderStatus::Completed ? CommissionStatus::Approved : CommissionStatus::Pending,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => null,
        ]);
    }

    public function reviewReject(AffiliateCommission $commission, User $reviewer, string $note): void
    {
        $commission->update([
            'status' => CommissionStatus::Cancelled,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }

    /** On completion: clear held commissions so they become withdrawable. */
    public function approveCommissions(Order $order): void
    {
        $order->affiliateCommissions()
            ->where('status', CommissionStatus::Pending->value)
            ->update(['status' => CommissionStatus::Approved->value]);
    }

    /** On cancel/return: void commissions that haven't been paid out yet. */
    public function cancelCommissions(Order $order): void
    {
        $order->affiliateCommissions()
            ->whereIn('status', [CommissionStatus::AwaitingReview->value, CommissionStatus::Pending->value, CommissionStatus::Approved->value])
            ->update(['status' => CommissionStatus::Cancelled->value]);
    }

    /** Generate a unique 6-char referral code (mixed-case alphanumeric, ~57B combinations). */
    public function generateCode(): string
    {
        do {
            $code = Str::random(6);
        } while (Affiliate::where('code', $code)->exists());

        return $code;
    }

    /** Create a withdrawal request against the available balance. */
    public function requestPayout(Affiliate $affiliate, float $amount): AffiliatePayout
    {
        $available = $affiliate->availableBalance();
        $min = $this->minPayout();

        if ($amount < $min) {
            throw ValidationException::withMessages([
                'amount' => 'Minimum penarikan adalah Rp '.number_format($min, 0, ',', '.').'.',
            ]);
        }
        if ($amount > $available) {
            throw ValidationException::withMessages([
                'amount' => 'Jumlah melebihi saldo yang tersedia (Rp '.number_format($available, 0, ',', '.').').',
            ]);
        }
        if (! $affiliate->bank_account_number) {
            throw ValidationException::withMessages([
                'amount' => 'Lengkapi data rekening bank terlebih dahulu.',
            ]);
        }

        // Masuk antrean keuangan hanya setelah link konfirmasi email diklik —
        // pembajak akun tanpa akses email tidak bisa mencairkan dana.
        return $affiliate->payouts()->create([
            'amount' => $amount,
            'status' => PayoutStatus::AwaitingConfirmation,
            'method' => 'bank_transfer',
            'bank_name' => $affiliate->bank_name,
            'bank_account_number' => $affiliate->bank_account_number,
            'bank_account_holder' => $affiliate->bank_account_holder,
            'requested_at' => now(),
        ]);
    }

    /**
     * Settle a payout: mark it paid and consume approved commissions up to its amount
     * (so the balance drops and those commissions can't be withdrawn twice).
     */
    public function settlePayout(AffiliatePayout $payout, ?int $actorId = null, ?string $reference = null): void
    {
        DB::transaction(function () use ($payout, $actorId, $reference) {
            $remaining = (float) $payout->amount;

            $commissions = $payout->affiliate->commissions()
                ->where('status', CommissionStatus::Approved->value)
                ->orderBy('id')
                ->get();

            foreach ($commissions as $commission) {
                if ($remaining <= 0) {
                    break;
                }
                $commission->update(['status' => CommissionStatus::Paid]);
                $remaining -= (float) $commission->amount;
            }

            $payout->update([
                'status' => PayoutStatus::Paid,
                'reference' => $reference ?: $payout->reference,
                'processed_by' => $actorId,
                'processed_at' => now(),
            ]);
        });
    }
}
