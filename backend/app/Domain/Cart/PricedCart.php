<?php

namespace App\Domain\Cart;

use App\Models\Cart;
use App\Models\Coupon;

final class PricedCart
{
    /**
     * @param  list<PricedLine>  $lines
     * @param  array<string, int>  $taxBreakdown
     * @param  list<array{code: string, name: string, description: string|null, amount: int, days_min: int, days_max: int}>  $shippingOptions
     * @param  array{code: string, name: string, description: string|null, amount: int, days_min: int, days_max: int}|null  $shippingMethod
     * @param  list<array{code: string, item: string, message: string}>  $warnings
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
