<?php

namespace App\Domain\Cart;

use App\Domain\Promotions\CouponService;
use App\Domain\Shipping\ShippingCalculator;
use App\Domain\Tax\TaxCalculator;
use App\Exceptions\ApiException;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\TaxClass;
use App\Support\Money\Money;
use App\Support\Settings\Settings;

/**
 * Server-authoritative pricing (ARCHITECTURE §6.5). The same result is shown in the
 * cart, used at checkout and snapshotted into the order. Client prices are never read.
 */
class CartPricer
{
    public function __construct(
        private readonly TaxCalculator $tax,
        private readonly ShippingCalculator $shipping,
        private readonly CouponService $coupons,
        private readonly Settings $settings,
    ) {}

    public function price(Cart $cart, bool $strictCoupon = false): PricedCart
    {
        $cart->loadMissing(['items.variant.product.media', 'items.variant.product.brand', 'items.variant.product.taxClass', 'items.variant.inventory']);
        $currency = $cart->currency;
        $defaultRate = TaxClass::default()->rate_bps ?? 0;

        $lines = [];
        $warnings = [];
        foreach ($cart->items as $item) {
            $variant = $item->variant;
            $product = $variant->product;
            $available = $variant->available();
            $unavailable = $variant->trashed() || ! $variant->is_active || $product->trashed() || $product->status->value !== 'active' || $available === 0;

            if ($unavailable) {
                $warnings[] = ['code' => 'unavailable', 'item' => $item->uuid, 'message' => "{$product->name} is no longer available."];
            } elseif ($available !== null && $item->quantity > $available) {
                $warnings[] = ['code' => 'quantity_reduced', 'item' => $item->uuid, 'message' => "Only {$available} of {$product->name} left."];
            }
            if ($item->unit_price_seen !== null && $item->unit_price_seen !== $variant->price) {
                $warnings[] = ['code' => 'price_changed', 'item' => $item->uuid, 'message' => "The price of {$product->name} has changed."];
            }

            $quantity = $available === null ? $item->quantity : min($item->quantity, $available);
            $lines[] = new PricedLine(
                item: $item,
                quantity: $unavailable ? 0 : $quantity,
                unitPrice: $variant->price,
                lineSubtotal: $unavailable ? 0 : $variant->price * $quantity,
                taxRateBps: $product->taxClass->rate_bps ?? $defaultRate,
                requiresShipping: $product->requiresShipping(),
                unavailable: $unavailable,
            );
        }

        $sellable = array_values(array_filter($lines, fn (PricedLine $l) => ! $l->unavailable && $l->quantity > 0));
        $subtotal = array_sum(array_map(fn (PricedLine $l) => $l->lineSubtotal, $sellable));
        $requiresShipping = (bool) array_filter($sellable, fn (PricedLine $l) => $l->requiresShipping);

        // Coupon
        $coupon = null;
        $couponError = null;
        if ($cart->coupon_code) {
            $candidate = $this->coupons->find($cart->coupon_code);
            try {
                if (! $candidate) {
                    throw new ApiException('This code is not valid.', 422, 'coupon_invalid', ['coupon' => ['This code is not valid.']]);
                }
                $this->coupons->validate($candidate, $subtotal, $cart->user_id, $cart->email);
                $coupon = $candidate;
            } catch (ApiException $e) {
                if ($strictCoupon) {
                    throw $e;
                }
                $couponError = $e->getMessage();
            }
        }

        $discount = $coupon ? $this->coupons->merchandiseDiscount($coupon, $subtotal) : 0;
        $this->allocateDiscount($sellable, $discount, $currency);

        // Shipping
        $country = $cart->shipping_address['country_code'] ?? null;
        $shippingOptions = $requiresShipping && $country ? $this->shipping->optionsFor($country, $subtotal - $discount)->all() : [];
        $selected = null;
        if ($requiresShipping && $shippingOptions) {
            $selected = collect($shippingOptions)->firstWhere('code', $cart->shipping_method_code) ?? null;
        }
        $shippingTotal = $selected['amount'] ?? 0;
        if ($coupon && $this->coupons->freeShipping($coupon)) {
            $discount += $shippingTotal;
            $shippingTotal = 0;
        }

        // Tax (inclusive; extracted per line from the discounted amount)
        $storeState = strtoupper((string) $this->settings->get('store.state_code', 'TN'));
        $destinationState = strtoupper((string) ($cart->shipping_address['state_code'] ?? $storeState));
        // No destination yet (or digital-only): estimate as an in-state supply until an address is given.
        $intraState = ! $requiresShipping || $country === null || ($country === 'IN' && $destinationState === $storeState);
        $breakdown = [];
        $taxTotal = 0;
        foreach ($sellable as $line) {
            $line->taxTotal = $this->tax->inclusiveTax($line->lineSubtotal - $line->discount, $line->taxRateBps);
            $taxTotal += $line->taxTotal;
            foreach ($this->tax->split($line->taxTotal, $intraState) as $code => $amount) {
                $breakdown[$code] = ($breakdown[$code] ?? 0) + $amount;
            }
        }

        $merchDiscount = array_sum(array_map(fn (PricedLine $l) => $l->discount, $sellable));
        $grandTotal = $subtotal - $merchDiscount + $shippingTotal;

        return new PricedCart(
            cart: $cart,
            lines: $lines,
            currency: $currency,
            subtotal: $subtotal,
            discountTotal: $discount,
            merchandiseDiscount: $merchDiscount,
            shippingTotal: $shippingTotal,
            taxTotal: $taxTotal,
            grandTotal: $grandTotal,
            taxBreakdown: $breakdown,
            requiresShipping: $requiresShipping,
            coupon: $coupon,
            couponError: $couponError,
            shippingOptions: $shippingOptions,
            shippingMethod: $selected,
            warnings: $warnings,
        );
    }

    /**
     * Spreads the merchandise discount across lines in proportion to their value,
     * so per-line tax stays correct and no paisa is lost.
     *
     * @param  list<PricedLine>  $lines
     */
    private function allocateDiscount(array $lines, int $discount, string $currency): void
    {
        if ($discount <= 0 || $lines === []) {
            return;
        }

        $parts = Money::of($discount, $currency)->allocate(array_map(fn (PricedLine $l) => max(1, $l->lineSubtotal), $lines));
        foreach ($lines as $i => $line) {
            $line->discount = min($parts[$i]->amount, $line->lineSubtotal);
        }
    }
}
