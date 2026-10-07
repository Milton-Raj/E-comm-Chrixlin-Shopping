<?php

namespace App\Domain\Promotions;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Coupon validation and discount calculation (PRD §35). All rules are data on the coupon.
 */
class CouponService
{
    public function find(string $code): ?Coupon
    {
        return Coupon::query()->where('code', strtoupper(trim($code)))->first();
    }

    /**
     * @throws ApiException with a machine-readable code when the coupon cannot be used
     */
    public function validate(Coupon $coupon, int $merchandiseTotal, ?int $userId, ?string $email): void
    {
        $fail = fn (string $message, string $code) => throw new ApiException($message, 422, $code, ['coupon' => [$message]]);

        if (! $coupon->is_active || ($coupon->starts_at && $coupon->starts_at->isFuture())) {
            $fail('This code is not valid.', 'coupon_invalid');
        }
        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            $fail('This code has expired.', 'coupon_expired');
        }
        if ($coupon->usage_limit !== null && $coupon->usage_count >= $coupon->usage_limit) {
            $fail('This code has reached its usage limit.', 'coupon_exhausted');
        }
        if ($coupon->min_order_total !== null && $merchandiseTotal < $coupon->min_order_total) {
            $fail('Your order does not meet the minimum for this code.', 'coupon_minimum_not_met');
        }

        if ($coupon->per_customer_limit !== null && ($userId || $email)) {
            $used = DB::table('coupon_usages')->where('coupon_id', $coupon->id)
                ->where(fn ($q) => $q->when($userId, fn ($q) => $q->orWhere('user_id', $userId))->when($email, fn ($q) => $q->orWhere('email', $email)))
                ->count();
            if ($used >= $coupon->per_customer_limit) {
                $fail('You have already used this code.', 'coupon_already_used');
            }
        }

        if ($coupon->first_order_only && ($userId || $email)) {
            $hasOrder = Order::query()
                ->where(fn ($q) => $q->when($userId, fn ($q) => $q->orWhere('user_id', $userId))->when($email, fn ($q) => $q->orWhere('email', $email)))
                ->where('payment_status', PaymentStatus::Captured)
                ->exists();
            if ($hasOrder) {
                $fail('This code is for first orders only.', 'coupon_first_order_only');
            }
        }
    }

    /**
     * Merchandise discount in minor units (free-shipping coupons discount shipping instead).
     */
    public function merchandiseDiscount(Coupon $coupon, int $merchandiseTotal): int
    {
        $discount = match ($coupon->type) {
            'percentage' => intdiv($merchandiseTotal * $coupon->value * 2 + 10_000, 20_000),
            'fixed' => $coupon->value,
            default => 0,
        };

        if ($coupon->max_discount !== null) {
            $discount = min($discount, $coupon->max_discount);
        }

        return max(0, min($discount, $merchandiseTotal));
    }

    public function freeShipping(Coupon $coupon): bool
    {
        return $coupon->type === 'free_shipping';
    }
}
