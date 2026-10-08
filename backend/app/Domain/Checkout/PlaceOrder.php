<?php

namespace App\Domain\Checkout;

use App\Domain\Cart\CartPricer;
use App\Domain\Cart\PricedCart;
use App\Domain\Inventory\InventoryService;
use App\Domain\Orders\Enums\FulfillmentStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\OrderNumberGenerator;
use App\Domain\Orders\OrderStateMachine;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\GatewayManager;
use App\Exceptions\ApiException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns a priced cart into a pending order with reserved stock and a payment
 * attempt (ARCHITECTURE §6.10). Prices come only from CartPricer.
 */
class PlaceOrder
{
    public function __construct(
        private readonly CartPricer $pricer,
        private readonly InventoryService $inventory,
        private readonly OrderNumberGenerator $numbers,
        private readonly OrderStateMachine $states,
        private readonly GatewayManager $gateways,
    ) {}

    /**
     * @return array{order: Order, payment: Payment, client_payload: array<string, mixed>, access_token: string|null}
     */
    public function handle(Cart $cart, string $gatewayKey, ?int $expectedTotal, Request $request): array
    {
        $gateway = $this->gateways->get($gatewayKey);

        $result = DB::transaction(function () use ($cart, $gatewayKey, $expectedTotal, $request) {
            $cart = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $priced = $this->pricer->price($cart, strictCoupon: true);
            $this->assertReady($priced, $expectedTotal);

            /** @var User|null $user */
            $user = $request->user();
            $order = Order::create([
                'order_number' => $this->numbers->next(),
                'user_id' => $user?->id,
                'cart_id' => $cart->id,
                'email' => $cart->email,
                'phone' => $cart->phone,
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Initiated,
                'fulfillment_status' => $priced->requiresShipping ? FulfillmentStatus::Unfulfilled : FulfillmentStatus::NotRequired,
                'currency' => $priced->currency,
                'subtotal' => $priced->subtotal,
                'discount_total' => $priced->discountTotal,
                'shipping_total' => $priced->shippingTotal,
                'tax_total' => $priced->taxTotal,
                'grand_total' => $priced->grandTotal,
                'prices_include_tax' => true,
                'tax_breakdown' => $priced->taxBreakdown,
                'coupon_code' => $priced->coupon?->code,
                'shipping_method' => $priced->shippingMethod,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 509),
                'placed_at' => now(),
            ]);
            $this->states->record($order, $user ? 'customer' : 'guest', $user?->id, 'Order placed');

            $reserve = [];
            foreach ($priced->sellableLines() as $line) {
                $variant = $line->item->variant;
                $product = $variant->product;
                $order->items()->create([
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'product_type' => $product->product_type->value,
                    'sku' => $variant->sku,
                    'name' => $product->name,
                    'variant_name' => $variant->name,
                    'image_path' => $product->media->first()?->path,
                    'unit_price' => $line->unitPrice,
                    'quantity' => $line->quantity,
                    'line_subtotal' => $line->lineSubtotal,
                    'discount_total' => $line->discount,
                    'tax_total' => $line->taxTotal,
                    'tax_rate_bps' => $line->taxRateBps,
                    'line_total' => $line->lineTotal(),
                    'requires_shipping' => $line->requiresShipping,
                ]);
                if ($variant->track_inventory) {
                    $reserve[$variant->id] = ($reserve[$variant->id] ?? 0) + $line->quantity;
                }
            }

            $address = $cart->shipping_address ?? [];
            if ($address !== []) {
                foreach (['shipping', 'billing'] as $type) {
                    $order->addresses()->create([
                        'type' => $type,
                        'name' => $address['name'] ?? '',
                        'phone' => $address['phone'] ?? $cart->phone,
                        'line1' => $address['line1'] ?? '',
                        'line2' => $address['line2'] ?? null,
                        'city' => $address['city'] ?? '',
                        'state_code' => $address['state_code'] ?? '',
                        'postal_code' => $address['postal_code'] ?? '',
                        'country_code' => $address['country_code'] ?? 'IN',
                    ]);
                }
            }

            $this->inventory->reserve($order, $reserve);

            $payment = Payment::create([
                'order_id' => $order->id, 'provider' => $gatewayKey, 'amount' => $order->grand_total,
                'currency' => $order->currency, 'status' => PaymentStatus::Initiated,
            ]);

            $accessToken = null;
            if (! $user) {
                $accessToken = Str::random(48);
                DB::table('order_access_tokens')->insert([
                    'order_id' => $order->id, 'token_hash' => hash('sha256', $accessToken),
                    'expires_at' => now()->addDays(30), 'created_at' => now(),
                ]);
            }

            return compact('order', 'payment', 'accessToken');
        });

        /** @var Payment $payment */
        $payment = $result['payment'];
        [$providerOrderId, $payload] = $gateway->createPayment($payment->setRelation('order', $result['order']));
        $payment->forceFill(['provider_order_id' => $providerOrderId, 'status' => PaymentStatus::Pending])->save();
        $result['order']->forceFill(['payment_status' => PaymentStatus::Pending])->save();

        Log::channel('orders')->info('Order placed.', ['order' => $result['order']->order_number, 'total' => $result['order']->grand_total, 'gateway' => $gatewayKey]);

        return ['order' => $result['order'], 'payment' => $payment, 'client_payload' => $payload, 'access_token' => $result['accessToken']];
    }

    private function assertReady(PricedCart $priced, ?int $expectedTotal): void
    {
        $cart = $priced->cart;
        $errors = [];

        foreach ($priced->warnings as $warning) {
            if (in_array($warning['code'], ['unavailable', 'quantity_reduced'], true)) {
                throw new ApiException($warning['message'].' Please review your bag.', 409, 'cart_changed');
            }
        }
        if ($priced->sellableLines() === []) {
            throw new ApiException('Your bag is empty.', 422, 'cart_empty');
        }
        if (! $cart->email) {
            $errors['email'] = ['Enter your email address.'];
        }
        if ($priced->requiresShipping) {
            if (! $cart->shipping_address) {
                $errors['address'] = ['Enter a delivery address.'];
            } elseif (! $priced->shippingMethod) {
                $errors['shipping_method'] = ['Choose a delivery method.'];
            }
        }
        if ($errors !== []) {
            throw new ApiException('Please complete your checkout details.', 422, 'checkout_incomplete', $errors);
        }

        if ($expectedTotal !== null && $expectedTotal !== $priced->grandTotal) {
            throw new ApiException('Your total has changed. Please review your order.', 409, 'price_changed');
        }
    }
}
