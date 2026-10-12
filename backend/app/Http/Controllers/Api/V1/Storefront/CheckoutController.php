<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Domain\Cart\CartPricer;
use App\Domain\Cart\CartService;
use App\Domain\Checkout\PlaceOrder;
use App\Domain\Checkout\SavedAddress;
use App\Domain\Marketing\MarketingSettings;
use App\Domain\Payments\GatewayManager;
use App\Domain\Shipping\ShippingCalculator;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Contact → Address → Shipping → Payment (PRD §26, §61). Details are saved on the
 * cart; place-order turns the priced cart into an order.
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly CartService $carts, private readonly CartPricer $pricer, private readonly GatewayManager $gateways, private readonly ShippingCalculator $shipping) {}

    public function show(Request $request, SavedAddress $saved): JsonResponse
    {
        $cart = $this->carts->currentOrCreate($request);

        return ApiResponse::success([
            'cart' => (new CartResource($this->pricer->price($cart)))->resolve($request),
            'gateways' => $this->gateways->available(),
            'saved_address' => $saved->forUser($request->user()),
        ]);
    }

    public function contact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+()\s-]{7,20}$/'],
        ]);
        $cart = $this->carts->currentOrCreate($request);
        $cart->forceFill(['email' => mb_strtolower($data['email']), 'phone' => $data['phone'] ?? null])->save();

        return $this->respond($request, $cart);
    }

    public function address(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+()\s-]{7,20}$/'],
            'line1' => ['required', 'string', 'max:200'],
            'line2' => ['nullable', 'string', 'max:200'],
            'city' => ['required', 'string', 'max:100'],
            'state_code' => ['required', 'string', 'max:8'],
            'postal_code' => ['required', 'string', 'max:16'],
            'country_code' => ['required', 'string', 'size:2'],
        ]);
        $data['country_code'] = strtoupper($data['country_code']);
        $data['state_code'] = strtoupper($data['state_code']);
        if ($data['country_code'] === 'IN' && ! preg_match('/^\d{6}$/', $data['postal_code'])) {
            throw new ApiException('Validation failed', 422, null, ['postal_code' => ['Enter a valid 6-digit PIN code.']]);
        }

        $cart = $this->carts->currentOrCreate($request);
        // Keep the chosen method if it delivers to this address; otherwise use the first one offered
        // (with a single method this is the only choice, so checkout skips the delivery step).
        $offered = $this->shipping->optionsFor($data['country_code'], 0)->pluck('code');
        $code = $offered->contains($cart->shipping_method_code) ? $cart->shipping_method_code : $offered->first();
        $cart->forceFill(['shipping_address' => $data, 'shipping_method_code' => $code])->save();

        return $this->respond($request, $cart);
    }

    /** Gift wrapping and a gift message (only when the owner offers it and the bag ships). */
    public function gift(Request $request, MarketingSettings $marketing): JsonResponse
    {
        $data = $request->validate([
            'gift_wrap' => ['required', 'boolean'],
            'gift_message' => ['nullable', 'string', 'max:300'],
        ]);
        if ($data['gift_wrap'] && ! $marketing->giftWrapEnabled()) {
            throw new ApiException('Gift wrapping is not available right now.', 409, 'gift_wrap_unavailable');
        }
        $cart = $this->carts->currentOrCreate($request);
        $message = $data['gift_wrap'] ? (trim((string) ($data['gift_message'] ?? '')) ?: null) : null;
        $cart->forceFill(['gift_wrap' => (bool) $data['gift_wrap'], 'gift_message' => $message])->save();

        return ApiResponse::success(new CartResource($this->pricer->price($cart->fresh() ?? $cart)));
    }

    public function shippingMethod(Request $request): JsonResponse
    {
        $cart = $this->carts->currentOrCreate($request);
        $country = is_array($cart->shipping_address) ? ($cart->shipping_address['country_code'] ?? null) : null;
        $offered = $this->shipping->optionsFor($country ?? (string) config('commerce.store.country'), 0)->pluck('code')->all();
        $data = $request->validate(['code' => ['required', 'string', Rule::in($offered)]]);
        $cart->forceFill(['shipping_method_code' => $data['code']])->save();

        return $this->respond($request, $cart);
    }

    public function placeOrder(Request $request, PlaceOrder $placeOrder): JsonResponse
    {
        $data = $request->validate([
            'gateway' => ['required', 'string', 'max:32'],
            'expected_total' => ['nullable', 'integer', 'min:0'],
        ]);
        $cart = $this->carts->current($request) ?? throw new ApiException('Your bag is empty.', 422, 'cart_empty');

        $result = $placeOrder->handle($cart, $data['gateway'], $data['expected_total'] ?? null, $request);

        return ApiResponse::success([
            'order_number' => $result['order']->order_number,
            'access_token' => $result['access_token'],
            'payment' => ['gateway' => $result['payment']->provider, 'client_payload' => $result['client_payload']],
        ], 'Order created. Complete your payment.', status: 201);
    }

    private function respond(Request $request, Cart $cart): JsonResponse
    {
        return ApiResponse::success((new CartResource($this->pricer->price($cart->fresh() ?? $cart)))->resolve($request));
    }
}
