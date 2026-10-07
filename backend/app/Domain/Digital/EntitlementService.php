<?php

namespace App\Domain\Digital;

use App\Models\DigitalEntitlement;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * Grants and revokes download rights for digital order items (PRD §16–§17).
 */
class EntitlementService
{
    public function grantForOrder(Order $order): void
    {
        $order->loadMissing('items.product.digital');

        foreach ($order->items->where('product_type', 'digital') as $item) {
            /** @var OrderItem $item */
            $settings = $item->product?->digital;
            DigitalEntitlement::query()->firstOrCreate(
                ['order_item_id' => $item->id],
                [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'email' => $order->email,
                    'product_id' => $item->product_id,
                    'download_limit' => $settings?->download_limit,
                    'expires_at' => $settings?->access_days ? now()->addDays($settings->access_days) : null,
                    'status' => 'available',
                ],
            );
        }
    }

    public function revokeForOrder(Order $order, string $status, string $reason): void
    {
        DigitalEntitlement::query()->where('order_id', $order->id)->whereIn('status', ['available', 'downloaded'])
            ->update(['status' => $status, 'revoked_at' => now(), 'revoke_reason' => $reason]);
    }
}
