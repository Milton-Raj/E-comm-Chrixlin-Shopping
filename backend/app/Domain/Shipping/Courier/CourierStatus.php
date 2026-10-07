<?php

namespace App\Domain\Shipping\Courier;

use App\Domain\Orders\Enums\OrderStatus;

/**
 * Maps Shiprocket's free-text tracking statuses onto our order stages.
 * Anything not listed is recorded on the shipment but does not move the order.
 */
final class CourierStatus
{
    private const SHIPPED = ['PICKED UP', 'SHIPPED', 'IN TRANSIT', 'REACHED AT DESTINATION HUB', 'REACHED DESTINATION HUB', 'MISROUTED', 'DELAYED', 'IN FLIGHT', 'REACHED WAREHOUSE', 'CUSTOM CLEARED'];

    private const EXCEPTIONS = ['UNDELIVERED', 'LOST', 'DAMAGED', 'DESTROYED', 'CANCELED', 'CANCELLED', 'PICKUP EXCEPTION', 'CANCELLATION REQUESTED', 'DISPOSED OFF', 'UNTRACEABLE'];

    public static function normalise(string $raw): string
    {
        return strtoupper(trim((string) preg_replace('/[\s_-]+/', ' ', $raw)));
    }

    /** The order stage this courier status proves, or null if it proves none. */
    public static function orderStatus(string $raw): ?OrderStatus
    {
        $status = self::normalise($raw);

        return match (true) {
            self::isException($status) => null,
            $status === 'DELIVERED' => OrderStatus::Delivered,
            $status === 'OUT FOR DELIVERY' => OrderStatus::OutForDelivery,
            in_array($status, self::SHIPPED, true), str_starts_with($status, 'IN TRANSIT') => OrderStatus::Shipped,
            default => null,
        };
    }

    /** Return-to-origin, loss, damage, cancellation: a person needs to look at it. */
    public static function isException(string $raw): bool
    {
        $status = self::normalise($raw);

        return str_starts_with($status, 'RTO') || in_array($status, self::EXCEPTIONS, true);
    }
}
