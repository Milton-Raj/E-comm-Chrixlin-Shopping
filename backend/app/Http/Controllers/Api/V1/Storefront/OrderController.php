<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Domain\Orders\Actions\CancelOrder;
use App\Domain\Payments\Actions\ProcessGatewayEvent;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\GatewayManager;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customer and guest order access. Guests prove ownership with the access token issued at checkout.
 */
class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()->where('user_id', $request->user()->getKey())
            ->whereNotNull('placed_at')->with('items.product')->latest('placed_at')->paginate(20);

        return ApiResponse::paginated($orders, OrderResource::class);
    }

    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = $this->ownedOrder($request, $orderNumber);
        $order->load(['items.product', 'addresses', 'history', 'shipments', 'entitlements.product.files', 'entitlements.order']);

        return ApiResponse::success(new OrderResource($order));
    }

    public function cancel(Request $request, string $orderNumber, CancelOrder $cancel): JsonResponse
    {
        $order = $this->ownedOrder($request, $orderNumber);
        $cancel->handle($order, 'customer', $request->user()?->getKey(), 'Cancelled by customer');

        return ApiResponse::success(message: 'Order cancelled.');
    }

    /**
     * The browser reports a completed payment; the server verifies it with the gateway
     * (signature + provider status, or the test gateway) before anything changes.
     */
    public function confirmPayment(Request $request, string $orderNumber, GatewayManager $gateways, ProcessGatewayEvent $process): JsonResponse
    {
        $order = $this->ownedOrder($request, $orderNumber);
        /** @var Payment|null $payment */
        $payment = $order->payments()->latest('id')->first();
        if (! $payment) {
            throw new ApiException('No payment found for this order.', 404, 'payment_not_found');
        }

        $event = $gateways->get($payment->provider)->confirm($payment, $request->all());
        if (! $event) {
            throw new ApiException('We could not verify this payment.', 422, 'payment_unverified');
        }

        $process->handle($payment->provider, $event);
        $order->refresh();

        return ApiResponse::success(['status' => $order->status->value, 'payment_status' => $order->payment_status->value]);
    }

    /** Starts a new payment attempt for an unpaid order (e.g. after a failed payment). */
    public function retryPayment(Request $request, string $orderNumber, GatewayManager $gateways): JsonResponse
    {
        $data = $request->validate(['gateway' => ['required', 'string', 'max:32']]);
        $order = $this->ownedOrder($request, $orderNumber);
        if (! $order->status->isOpenForPayment() || $order->payment_status === PaymentStatus::Captured) {
            throw new ApiException('This order can no longer be paid.', 409, 'not_payable');
        }

        $gateway = $gateways->get($data['gateway']);
        $payment = DB::transaction(function () use ($order, $gateway) {
            $order->payments()->whereIn('status', [PaymentStatus::Initiated, PaymentStatus::Pending])->update(['status' => PaymentStatus::Cancelled]);

            return Payment::create(['order_id' => $order->id, 'provider' => $gateway->key(), 'amount' => $order->grand_total, 'currency' => $order->currency, 'status' => PaymentStatus::Initiated]);
        });
        [$providerOrderId, $payload] = $gateway->createPayment($payment->setRelation('order', $order));
        $payment->forceFill(['provider_order_id' => $providerOrderId, 'status' => PaymentStatus::Pending])->save();
        $order->forceFill(['payment_status' => PaymentStatus::Pending])->save();

        return ApiResponse::success(['gateway' => $gateway->key(), 'client_payload' => $payload]);
    }

    private function ownedOrder(Request $request, string $orderNumber): Order
    {
        $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();
        $userId = $request->user()?->getKey();

        if ($userId !== null && $order->user_id === $userId) {
            return $order;
        }

        $token = (string) ($request->header('X-Order-Token') ?: $request->query('access_token', ''));
        if ($token !== '' && DB::table('order_access_tokens')->where('order_id', $order->id)
            ->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->exists()) {
            return $order;
        }

        abort(404); // never reveal that someone else's order exists
    }
}
