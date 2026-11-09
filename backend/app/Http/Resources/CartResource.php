<?php

namespace App\Http\Resources;

use App\Domain\Cart\PricedCart;
use App\Domain\Cart\PricedLine;
use App\Domain\Marketing\MarketingSettings;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Priced cart; every amount is server-computed (ARCHITECTURE §6.5) and given in the
 * currency the shopper pays in (D21): store-currency prices converted at today's rate.
 *
 * @property PricedCart $resource
 */
class CartResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $priced = $this->resource;
        $cart = $priced->cart;
        $currency = $priced->chargeCurrency();
        $money = fn (int $amount) => Money::of($priced->present($amount), $currency)->toArray();
        $minor = fn (int $amount) => Money::of($amount, $currency)->toArray(); // already converted
        $shippingOption = fn (array $o) => [...$o, 'amount' => $money($o['amount']), 'free_over' => $o['free_over'] === null ? null : $priced->present($o['free_over'])];

        return [
            'uuid' => $cart->uuid,
            'item_count' => $priced->itemCount(),
            'currency' => $currency,
            'base_currency' => $priced->currency,
            'exchange_rate' => $priced->presentment?->rate,
            'requires_shipping' => $priced->requiresShipping,
            'items' => array_map(function (PricedLine $line) use ($money, $minor, $priced) {
                $variant = $line->item->variant;
                $product = $variant->product;
                $media = $product->media->first();

                return [
                    'uuid' => $line->item->uuid,
                    'quantity' => $line->item->quantity,
                    'product' => [
                        'uuid' => $product->uuid,
                        'slug' => $product->slug,
                        'name' => $product->name,
                        'product_type' => $product->product_type->value,
                        'brand' => $product->brand?->name,
                        'image' => $media ? ['url' => $media->url(), 'alt' => $media->alt_text ?? $product->name] : null,
                    ],
                    'variant' => ['uuid' => $variant->uuid, 'sku' => $variant->sku, 'name' => $variant->name],
                    'unit_price' => $money($line->unitPrice),
                    'line_total' => $minor($priced->presentLine($line)),
                    'available' => ! $line->unavailable,
                    'max_quantity' => $product->product_type->tracksInventory() ? min(10, (int) ($variant->available() ?? 10)) : 1,
                ];
            }, $priced->lines),
            'totals' => [
                'subtotal' => $minor($priced->chargeSubtotal()),
                'discount' => $money($priced->discountTotal),
                'shipping' => $money($priced->shippingTotal),
                'gift_wrap' => $money($priced->giftWrapTotal),
                'tax' => $money($priced->taxTotal),
                'total' => $minor($priced->chargeTotal()),
            ],
            'tax_breakdown' => collect($priced->taxBreakdown)->map(fn (int $amount, string $code) => ['code' => $code, 'amount' => $money($amount)])->values(),
            'tax_inclusive' => true,
            'coupon' => $priced->coupon ? ['code' => $priced->coupon->code, 'description' => $priced->coupon->description] : null,
            'coupon_error' => $priced->couponError,
            'contact' => ['email' => $cart->email, 'phone' => $cart->phone],
            'shipping_address' => $cart->shipping_address,
            'shipping_method' => $priced->shippingMethod ? $shippingOption($priced->shippingMethod) : null,
            'shipping_options' => array_map($shippingOption, $priced->shippingOptions),
            'gift' => [
                // Offered only for bags that ship; the price is the owner's current setting.
                'available' => $priced->requiresShipping && app(MarketingSettings::class)->giftWrapEnabled(),
                'price' => $money(app(MarketingSettings::class)->giftWrapPrice()),
                'wrap' => $priced->giftWrapped,
                'message' => $priced->giftWrapped ? $cart->gift_message : null,
            ],
            'free_delivery' => $priced->freeDelivery ? [
                'threshold' => $money($priced->freeDelivery['threshold']),
                'remaining' => $money($priced->freeDelivery['remaining']),
                'qualified' => $priced->freeDelivery['remaining'] === 0,
            ] : null,
            'warnings' => $priced->warnings,
        ];
    }
}
