<?php

namespace App\Domain\Cart;

use App\Models\CartItem;

final class PricedLine
{
    public int $discount = 0;

    public int $taxTotal = 0;

    public function __construct(
        public readonly CartItem $item,
        public readonly int $quantity,
        public readonly int $unitPrice,
        public readonly int $lineSubtotal,
        public readonly int $taxRateBps,
        public readonly bool $requiresShipping,
        public readonly bool $unavailable,
    ) {}

    public function lineTotal(): int
    {
        return $this->lineSubtotal - $this->discount;
    }
}
