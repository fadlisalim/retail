<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;

/**
 * Server-side coupon validation & discount computation. The client never gets to
 * decide a discount — it only submits a code, and this class is the single source
 * of truth for whether it applies and for how much.
 */
class CouponService
{
    /**
     * @return array{coupon: ?Coupon, discount: float, free_shipping: bool, error: ?string}
     */
    public function evaluate(?string $code, float $subtotal, ?int $userId = null): array
    {
        $fail = fn (string $error) => [
            'coupon' => null, 'discount' => 0.0, 'free_shipping' => false, 'error' => $error,
        ];

        if (! $code) {
            return $fail('');
        }

        /** @var Coupon|null $coupon */
        $coupon = Coupon::active()->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])->first();

        if (! $coupon) {
            return $fail('Kode voucher tidak ditemukan atau sudah tidak berlaku.');
        }

        if ($coupon->user_id && $coupon->user_id !== $userId) {
            return $fail('Voucher ini tidak berlaku untuk akun Anda.');
        }

        if ($subtotal < (float) $coupon->min_subtotal) {
            return $fail('Minimum belanja '.rupiah($coupon->min_subtotal).' untuk memakai voucher ini.');
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return $fail('Kuota voucher sudah habis.');
        }

        if ($coupon->usage_limit_per_user !== null && $userId) {
            $usedByUser = CouponUsage::where('coupon_id', $coupon->id)->where('user_id', $userId)->count();
            if ($usedByUser >= $coupon->usage_limit_per_user) {
                return $fail('Anda sudah mencapai batas pemakaian voucher ini.');
            }
        }

        $freeShipping = $coupon->type === 'free_shipping';
        $discount = $this->discountAmount($coupon, $subtotal);

        return ['coupon' => $coupon, 'discount' => $discount, 'free_shipping' => $freeShipping, 'error' => null];
    }

    private function discountAmount(Coupon $coupon, float $subtotal): float
    {
        $discount = match ($coupon->type) {
            'percent' => $subtotal * (float) $coupon->value / 100,
            'fixed' => (float) $coupon->value,
            default => 0.0, // free_shipping applies to shipping, not the subtotal
        };

        if ($coupon->max_discount !== null) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return round(min($discount, $subtotal), 2);
    }
}
