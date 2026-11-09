<?php

namespace App\Domain\Cart;

use App\Domain\Localization\Presentment;
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
        /** Set when the shopper sees and pays in another currency (amounts above stay in the store currency). */
        public readonly ?Presentment $presentment = null,
    ) {}

    /** A store-currency amount in the currency the shopper pays in. */
    public function present(int $amount): int
    {
        return $this->presentment ? $this->presentment->convert($amount) : $amount;
    }

    /** Converted unit price × quantity, so a line always equals what its unit price says. */
    public function presentLine(PricedLine $line): int
    {
        return $this->presentment ? $this->presentment->convert($line->unitPrice) * $line->quantity : $line->lineSubtotal;
    }

    public function chargeCurrency(): string
    {
        return $this->presentment->currency ?? $this->currency;
    }

    public function chargeSubtotal(): int
    {
        return array_sum(array_map(fn (PricedLine $l) => $this->presentLine($l), $this->sellableLines()));
    }

    /**
     * The amount the gateway is asked for. Abroad it is built from the converted parts
     * shown to the shopper (lines − discount + delivery + gift wrap), so the figures add up.
     */
    public function chargeTotal(): int
    {
        if (! $this->presentment) {
            return $this->grandTotal;
        }

        return $this->chargeSubtotal() - $this->present($this->merchandiseDiscount) + $this->present($this->shippingTotal) + $this->present($this->giftWrapTotal);
    }

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
