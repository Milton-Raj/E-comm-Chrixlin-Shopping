<?php

namespace App\Http\Resources;

use App\Domain\Cart\PricedCart;
use App\Domain\Cart\PricedLine;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Priced cart; every amount is server-computed (ARCHITECTURE §6.5).
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
        $money = fn (int $amount) => Money::of($amount, $priced->currency)->toArray();

        return [
            'uuid' => $cart->uuid,
            'item_count' => $priced->itemCount(),
            'currency' => $priced->currency,
            'requires_shipping' => $priced->requiresShipping,
            'items' => array_map(function (PricedLine $line) use ($money) {
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
                    'line_total' => $money($line->lineSubtotal),
                    'available' => ! $line->unavailable,
                    'max_quantity' => $product->product_type->tracksInventory() ? min(10, (int) ($variant->available() ?? 10)) : 1,
                ];
            }, $priced->lines),
            'totals' => [
                'subtotal' => $money($priced->subtotal),
                'discount' => $money($priced->discountTotal),
                'shipping' => $money($priced->shippingTotal),
                'tax' => $money($priced->taxTotal),
                'total' => $money($priced->grandTotal),
            ],
            'tax_breakdown' => collect($priced->taxBreakdown)->map(fn (int $amount, string $code) => ['code' => $code, 'amount' => $money($amount)])->values(),
            'tax_inclusive' => true,
            'coupon' => $priced->coupon ? ['code' => $priced->coupon->code, 'description' => $priced->coupon->description] : null,
            'coupon_error' => $priced->couponError,
            'contact' => ['email' => $cart->email, 'phone' => $cart->phone],
            'shipping_address' => $cart->shipping_address,
            'shipping_method' => $priced->shippingMethod ? [...$priced->shippingMethod, 'amount' => $money($priced->shippingMethod['amount'])] : null,
            'shipping_options' => array_map(fn (array $o) => [...$o, 'amount' => $money($o['amount'])], $priced->shippingOptions),
            'warnings' => $priced->warnings,
        ];
    }
}
