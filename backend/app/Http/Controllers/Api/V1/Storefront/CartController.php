<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Domain\Cart\CartPricer;
use App\Domain\Cart\CartService;
use App\Domain\Catalog\Recommendations;
use App\Domain\Promotions\CouponService;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Http\Resources\ProductResource;
use App\Models\Cart;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $carts, private readonly CartPricer $pricer) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->carts->currentOrCreate($request);

        return ApiResponse::success(new CartResource($this->pricer->price($cart)));
    }

    public function addItem(Request $request): JsonResponse
    {
        $data = $request->validate(['variant_uuid' => ['required', 'uuid'], 'quantity' => ['required', 'integer', 'min:1', 'max:10']]);
        $cart = $this->carts->currentOrCreate($request);
        $this->carts->addItem($cart, $data['variant_uuid'], (int) $data['quantity']);

        return ApiResponse::success(new CartResource($this->pricer->price($cart->fresh() ?? $cart)), 'Added to your bag.', status: 201);
    }

    public function updateItem(Request $request, string $item): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:10']]);
        $cart = $this->carts->currentOrCreate($request);
        $this->carts->updateItem($cart, $item, (int) $data['quantity']);

        return ApiResponse::success(new CartResource($this->pricer->price($cart->fresh() ?? $cart)));
    }

    public function removeItem(Request $request, string $item): JsonResponse
    {
        $cart = $this->carts->currentOrCreate($request);
        $this->carts->removeItem($cart, $item);

        return ApiResponse::success(new CartResource($this->pricer->price($cart->fresh() ?? $cart)));
    }

    public function applyCoupon(Request $request, CouponService $coupons): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $cart = $this->carts->currentOrCreate($request);
        $coupon = $coupons->find($data['code']);
        if (! $coupon) {
            throw new ApiException('This code is not valid.', 422, 'coupon_invalid', ['coupon' => ['This code is not valid.']]);
        }

        $cart->forceFill(['coupon_code' => $coupon->code])->save();
        try {
            $priced = $this->pricer->price($cart->fresh() ?? $cart, strictCoupon: true);
        } catch (ApiException $e) {
            $cart->forceFill(['coupon_code' => null])->save();
            throw new ApiException($e->getMessage(), 422, $e->errorCode, ['coupon' => [$e->getMessage()]]);
        }

        return ApiResponse::success(new CartResource($priced), 'Code applied.');
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->carts->currentOrCreate($request);
        $cart->forceFill(['coupon_code' => null])->save();

        return ApiResponse::success(new CartResource($this->pricer->price($cart)));
    }

    /** "Pairs well with" for the bag: suggestions based on what is in it, excluding those items. */
    public function suggestions(Request $request, Recommendations $recommendations): JsonResponse
    {
        $cart = $this->carts->current($request);
        $products = $cart ? $cart->items()->with('variant.product')->get()->map(fn ($item) => $item->variant->product)->filter()->unique('id')->values() : collect();

        return ApiResponse::success(ProductResource::collection($recommendations->forProducts($products)));
    }

    /**
     * Signed link from the abandoned-bag email: reattaches a guest bag to this browser,
     * then sends the shopper to it. Signed-in bags are never handed to a cookie.
     */
    public function restore(Cart $cart): RedirectResponse
    {
        if ($cart->status === 'active' && $cart->user_id === null) {
            $this->carts->queueCookie($cart);
        }

        return redirect()->away(rtrim((string) config('commerce.frontend_url'), '/').'/cart');
    }
}
