<?php

namespace App\Domain\Cart;

use App\Models\Cart;
use App\Models\Coupon;

final class PricedCart
{
    /**
     * @param  list<PricedLine>  $lines
     * @param  array<string, int>  $taxBreakdown
     * @param  list<array{code: string, name: string, description: string|null, amount: int, free_over: int|null, days_min: int, days_max: int}>  $shippingOptions
     * @param  array{code: string, name: string, description: string|null, amount: int, free_over: int|null, days_min: int, days_max: int}|null  $shippingMethod
     * @param  list<array{code: string, item: string, message: string}>  $warnings
     * @param  array{threshold: int, remaining: int}|null  $freeDelivery  progress towards the selected method's free-delivery amount
     */
    public function __construct(
        public readonly Cart $cart,
        public readonly array $lines,
        public readonly string $currency,
        public readonly int $subtotal,
        public readonly int $discountTotal,
        public readonly int $merchandiseDiscount,
        public readonly int $shippingTotal,
        public readonly int $taxTotal,
        public readonly int $grandTotal,
        public readonly array $taxBreakdown,
        public readonly bool $requiresShipping,
        public readonly ?Coupon $coupon,
        public readonly ?string $couponError,
        public readonly array $shippingOptions,
        public readonly ?array $shippingMethod,
        public readonly array $warnings,
        public readonly ?array $freeDelivery = null,
        public readonly bool $giftWrapped = false,
        public readonly int $giftWrapTotal = 0,
        public readonly int $giftWrapTax = 0,
    ) {}

    /** @return list<PricedLine> */
    public function sellableLines(): array
    {
        return array_values(array_filter($this->lines, fn (PricedLine $l) => ! $l->unavailable && $l->quantity > 0));
    }

    public function itemCount(): int
    {
        return array_sum(array_map(fn (PricedLine $l) => $l->item->quantity, $this->lines));
    }
}
