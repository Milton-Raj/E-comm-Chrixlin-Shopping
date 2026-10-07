<?php

namespace App\Domain\Orders\Enums;

/**
 * Order lifecycle (PRD §19, ARCHITECTURE §6.10). Transitions are enforced only
 * through OrderStateMachine.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case PaymentProcessing = 'payment_processing';
    case Paid = 'paid';
    case Processing = 'processing';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case RefundRequested = 'refund_requested';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Failed = 'failed';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::PaymentProcessing, self::Paid, self::Cancelled, self::Failed],
            self::PaymentProcessing => [self::Paid, self::Failed, self::Cancelled, self::Pending],
            self::Paid => [self::Processing, self::Delivered, self::Cancelled, self::RefundRequested, self::Refunded, self::PartiallyRefunded],
            self::Processing => [self::Packed, self::Shipped, self::Cancelled, self::Refunded, self::PartiallyRefunded],
            self::Packed => [self::Shipped, self::Cancelled, self::Refunded],
            self::Shipped => [self::OutForDelivery, self::Delivered],
            self::OutForDelivery => [self::Delivered],
            self::Delivered => [self::RefundRequested, self::Refunded, self::PartiallyRefunded],
            self::RefundRequested => [self::Refunded, self::PartiallyRefunded, self::Delivered],
            self::PartiallyRefunded => [self::RefundRequested, self::Refunded],
            self::Failed => [self::Pending],
            self::Cancelled, self::Refunded => [],
        };
    }

    /** @return list<self> stages a shipped-goods order moves through after payment, in order */
    public static function fulfilmentPath(): array
    {
        return [self::Paid, self::Processing, self::Packed, self::Shipped, self::OutForDelivery, self::Delivered];
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function isOpenForPayment(): bool
    {
        return in_array($this, [self::Pending, self::PaymentProcessing, self::Failed], true);
    }
}
