<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Invoices\InvoiceDocument;
use App\Domain\Invoices\IssueInvoice;
use App\Domain\Orders\Actions\CancelOrder;
use App\Domain\Orders\Actions\RefundOrder;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\OrderStateMachine;
use App\Domain\Shipping\Actions\BookCourierPickup;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Support\Audit\Audit;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', 'string', 'max:32'],
            'q' => ['nullable', 'string', 'max:100'],
            'attention' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $orders = Order::query()->whereNotNull('placed_at')->with('items.product')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['payment_status'] ?? null, fn ($q, $s) => $q->where('payment_status', $s))
            ->when($filters['attention'] ?? false, fn ($q) => $q->where('requires_attention', true))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('order_number', 'like', "%{$t}%")->orWhere('email', 'like', "%{$t}%")))
            ->latest('placed_at')->paginate(25);

        return ApiResponse::paginated($orders, OrderResource::class);
    }

    public function show(string $order): JsonResponse
    {
        return ApiResponse::success((new OrderResource($this->find($order)))->forAdmin());
    }

    /**
     * Moves an order to a later stage (intermediate stages are recorded). Reaching "Packed"
     * books the courier pickup automatically when Shiprocket is configured.
     */
    public function transition(Request $request, string $order, OrderStateMachine $states, BookCourierPickup $courier): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', Rule::in([OrderStatus::Processing->value, OrderStatus::Packed->value, OrderStatus::OutForDelivery->value, OrderStatus::Delivered->value])],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        $model = $this->find($order);
        $before = ['status' => $model->status->value];

        DB::transaction(fn () => $states->advance($model, OrderStatus::from($data['to']), 'staff', $request->user()->getKey(), $data['reason'] ?? null));
        Audit::record('order.status_changed', $model, $before, ['status' => $model->status->value], $request->user());

        $message = 'Order updated.';
        if ($model->status === OrderStatus::Packed && $model->requiresShipping() && $courier->enabled()) {
            $shipment = $courier->handle($model, $request->user());
            $message = $shipment->status === 'failed'
                ? 'Marked packed, but the Shiprocket booking failed: '.$shipment->last_error
                : "Marked packed. Shiprocket pickup booked with {$shipment->carrier} (AWB {$shipment->tracking_number}).";
        }

        return ApiResponse::success((new OrderResource($this->find($order)))->forAdmin(), $message);
    }

    /** Downloads the GST tax invoice; issues it first for paid orders placed before invoicing existed. */
    public function invoice(string $order, IssueInvoice $issue, InvoiceDocument $document): Response
    {
        $model = $this->find($order);
        abort_unless(in_array($model->payment_status->value, ['captured', 'partially_refunded', 'refunded'], true), 409, 'Unpaid orders have no invoice.');
        $invoice = $model->invoice()->first() ?? $issue->handle($model);

        return response($document->pdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$document->filename($invoice).'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Retries (or makes) the Shiprocket booking for a packed order. */
    public function bookCourier(Request $request, string $order, BookCourierPickup $courier): JsonResponse
    {
        $model = $this->find($order);
        if (! $courier->enabled()) {
            throw new ApiException('Shiprocket is not configured on the server.', 409, 'courier_not_configured');
        }
        if ($model->status !== OrderStatus::Packed) {
            throw new ApiException('Only packed orders can be booked for pickup.', 409, 'invalid_transition');
        }
        $shipment = $courier->handle($model, $request->user());
        if ($shipment->status === 'failed') {
            throw new ApiException((string) $shipment->last_error, 502, 'courier_error');
        }

        return ApiResponse::success((new OrderResource($this->find($order)))->forAdmin(), "Pickup booked with {$shipment->carrier} (AWB {$shipment->tracking_number}).");
    }

    public function ship(Request $request, string $order, OrderStateMachine $states): JsonResponse
    {
        $data = $request->validate([
            'carrier' => ['required', 'string', 'max:80'],
            'tracking_number' => ['required', 'string', 'max:80'],
            'tracking_url' => ['nullable', 'url:https', 'max:300'],
        ]);
        $model = $this->find($order);
        if (! $model->requiresShipping()) {
            throw new ApiException('This order has nothing to ship.', 409, 'not_shippable');
        }

        DB::transaction(function () use ($model, $data, $states, $request) {
            $model->shipments()->create([...$data, 'shipped_at' => now()]);
            if ($model->status === OrderStatus::Paid) {
                $states->transition($model, OrderStatus::Processing, 'staff', $request->user()->getKey());
            }
            $states->transition($model, OrderStatus::Shipped, 'staff', $request->user()->getKey(), "Shipped via {$data['carrier']}");
        });
        Audit::record('order.shipped', $model, null, $data, $request->user());

        return ApiResponse::success((new OrderResource($this->find($order)))->forAdmin(), 'Shipment recorded.');
    }

    public function refund(Request $request, string $order, RefundOrder $refund): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:200'],
            'restock' => ['boolean'],
        ]);
        $refund->handle($this->find($order), (int) $data['amount'], $data['reason'], (bool) ($data['restock'] ?? false), $request->user());

        return ApiResponse::success((new OrderResource($this->find($order)))->forAdmin(), 'Refund processed.');
    }

    public function cancel(Request $request, string $order, CancelOrder $cancel): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $model = $this->find($order);
        $cancel->handle($model, 'staff', $request->user()->getKey(), $data['reason']);
        Audit::record('order.cancelled', $model, null, ['reason' => $data['reason']], $request->user());

        return ApiResponse::success((new OrderResource($this->find($order)))->forAdmin(), 'Order cancelled.');
    }

    private function find(string $orderNumber): Order
    {
        return Order::query()->where('order_number', $orderNumber)
            ->with(['items.product', 'addresses', 'history', 'shipments', 'payments', 'refunds', 'user', 'entitlements.product.files', 'entitlements.order', 'invoice'])
            ->firstOrFail();
    }
}
