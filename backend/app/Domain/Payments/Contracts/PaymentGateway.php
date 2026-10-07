<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\GatewayEvent;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Every payment provider sits behind this contract (PRD §28, ARCHITECTURE §6.11).
 */
interface PaymentGateway
{
    public function key(): string;

    /**
     * Creates the provider-side order/intent. Returns [provider_order_id, client payload for the browser].
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function createPayment(Payment $payment): array;

    /**
     * Turns a browser confirmation into a server-verified event, or null if unverifiable.
     * Never trusts the browser on its own: implementations verify signatures and/or ask the provider.
     *
     * @param  array<string, mixed>  $data
     */
    public function confirm(Payment $payment, array $data): ?GatewayEvent;

    public function verifyWebhook(Request $request): bool;

    public function parseWebhook(Request $request): ?GatewayEvent;

    /** @return string provider refund id */
    public function refund(Payment $payment, int $amount, string $reason): string;
}
