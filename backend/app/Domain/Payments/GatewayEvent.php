<?php

namespace App\Domain\Payments;

/** Provider-agnostic payment event. */
final class GatewayEvent
{
    public const CAPTURED = 'captured';

    public const FAILED = 'failed';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $type,
        public readonly ?string $providerOrderId,
        public readonly ?string $providerPaymentId,
        public readonly ?int $amount,
        public readonly ?string $currency,
        public readonly ?string $method = null,
        public readonly ?string $failureMessage = null,
        public readonly array $payload = [],
    ) {}
}
