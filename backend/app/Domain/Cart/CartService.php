<?php

namespace App\Domain\Cart;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Exceptions\ApiException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Guest carts live behind an httpOnly `cart_token` cookie; signed-in carts belong
 * to the user. On login the guest cart is merged into the user's cart (PRD §21).
 */
class CartService
{
    public const COOKIE = 'cart_token';

    public const MAX_QUANTITY = 10;

    /** Finds the current cart without creating one. */
    public function current(Request $request): ?Cart
    {
        $user = $request->user();
        if ($user instanceof User) {
            $cart = Cart::query()->where('user_id', $user->id)->where('status', 'active')->latest('id')->first();
            if ($cart) {
                return $cart;
            }
        }

        $token = (string) $request->cookie(self::COOKIE);
        if (! Str::isUuid($token)) {
            return null;
        }

        $cart = Cart::query()->where('token', $token)->where('status', 'active')->first();
        if ($cart && $user instanceof User && $cart->user_id === null) {
            $cart->forceFill(['user_id' => $user->id])->save();
        }

        return $cart && ($cart->user_id === null || $cart->user_id === $user?->getKey()) ? $cart : null;
    }

    public function currentOrCreate(Request $request): Cart
    {
        $cart = $this->current($request);
        if ($cart) {
            return $cart;
        }

        $cart = Cart::create([
            'token' => (string) Str::uuid(),
            'user_id' => $request->user()?->getKey(),
            'status' => 'active',
            'currency' => (string) config('commerce.store.currency'),
            'email' => $request->user()?->email,
            'last_activity_at' => now(),
        ]);
        $this->queueCookie($cart);

        return $cart;
    }

    public function queueCookie(Cart $cart): void
    {
        Cookie::queue(Cookie::make(self::COOKIE, $cart->token, 60 * 24 * 30, httpOnly: true, sameSite: 'lax'));
    }

    public function addItem(Cart $cart, string $variantUuid, int $quantity): CartItem
    {
        return DB::transaction(function () use ($cart, $variantUuid, $quantity) {
            $variant = $this->sellableVariant($variantUuid);
            $item = CartItem::query()->firstOrNew(['cart_id' => $cart->id, 'variant_id' => $variant->id]);
            $item->quantity = $this->clampQuantity($variant, ($item->exists ? $item->quantity : 0) + $quantity);
            $item->unit_price_seen = $variant->price;
            $item->save();
            $this->touch($cart);

            return $item;
        });
    }

    public function updateItem(Cart $cart, string $itemUuid, int $quantity): void
    {
        $item = $cart->items()->where('uuid', $itemUuid)->firstOrFail();
        if ($quantity <= 0) {
            $item->delete();
        } else {
            $item->forceFill(['quantity' => $this->clampQuantity($item->variant, $quantity)])->save();
        }
        $this->touch($cart);
    }

    public function removeItem(Cart $cart, string $itemUuid): void
    {
        $cart->items()->where('uuid', $itemUuid)->delete();
        $this->touch($cart);
    }

    /**
     * Merges a guest cart into the user's cart. Idempotent: a merged cart is never merged twice.
     */
    public function mergeGuestCart(?string $guestToken, User $user): void
    {
        if (! $guestToken || ! Str::isUuid($guestToken)) {
            return;
        }

        DB::transaction(function () use ($guestToken, $user) {
            $guest = Cart::query()->where('token', $guestToken)->where('status', 'active')->lockForUpdate()->first();
            if (! $guest || ($guest->user_id !== null && $guest->user_id !== $user->id)) {
                return;
            }

            $userCart = Cart::query()->where('user_id', $user->id)->where('status', 'active')->where('id', '!=', $guest->id)->latest('id')->first();
            if (! $userCart) {
                $guest->forceFill(['user_id' => $user->id, 'email' => $guest->email ?? $user->email])->save();

                return;
            }

            foreach ($guest->items as $item) {
                $existing = $userCart->items()->where('variant_id', $item->variant_id)->first();
                $quantity = $this->clampQuantity($item->variant, $item->quantity + ($existing->quantity ?? 0));
                $userCart->items()->updateOrCreate(['variant_id' => $item->variant_id], ['quantity' => $quantity, 'unit_price_seen' => $item->unit_price_seen]);
            }

            $guest->forceFill(['status' => 'merged'])->save();
            $this->touch($userCart);
        });
    }

    private function sellableVariant(string $uuid): ProductVariant
    {
        $variant = ProductVariant::query()->with(['product', 'inventory'])->where('uuid', $uuid)->where('is_active', true)->first();

        if (! $variant || $variant->product->status !== ProductStatus::Active) {
            throw new ApiException('This item is no longer available.', 422, 'variant_unavailable', ['variant_uuid' => ['This item is no longer available.']]);
        }

        if ($variant->available() === 0) {
            throw new ApiException('This item is sold out.', 409, 'out_of_stock');
        }

        return $variant;
    }

    private function clampQuantity(ProductVariant $variant, int $quantity): int
    {
        $max = self::MAX_QUANTITY;
        if ($variant->product->product_type->tracksInventory()) {
            $max = min($max, $variant->available() ?? $max);
        } else {
            $max = 1; // one licence per digital product per order
        }

        return max(1, min($quantity, $max));
    }

    private function touch(Cart $cart): void
    {
        $cart->forceFill(['last_activity_at' => now()])->save();
    }
}
