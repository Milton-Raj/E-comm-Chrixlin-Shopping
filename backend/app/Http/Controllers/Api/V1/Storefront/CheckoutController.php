<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Domain\Cart\CartPricer;
use App\Domain\Cart\CartService;
use App\Domain\Checkout\PlaceOrder;
use App\Domain\Checkout\SavedAddress;
use App\Domain\Payments\GatewayManager;
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
    public function __construct(private readonly CartService $carts, private readonly CartPricer $pricer, private readonly GatewayManager $gateways) {}

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
        $cart->forceFill(['shipping_address' => $data, 'shipping_method_code' => $cart->shipping_method_code ?? 'standard'])->save();

        return $this->respond($request, $cart);
    }

    public function shippingMethod(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', Rule::in(['standard', 'express'])]]);
        $cart = $this->carts->currentOrCreate($request);
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
