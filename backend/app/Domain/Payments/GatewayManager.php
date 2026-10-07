<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Gateways\RazorpayGateway;
use App\Domain\Payments\Gateways\TestGateway;
use App\Exceptions\ApiException;

/**
 * Resolves enabled gateways from configuration. The test gateway can never be enabled in production.
 */
class GatewayManager
{
    /** @return list<array{key: string, name: string, description: string}> */
    public function available(): array
    {
        $list = [];
        if ($this->razorpayConfigured()) {
            $list[] = ['key' => 'razorpay', 'name' => 'Razorpay', 'description' => 'UPI, cards, net banking and wallets'];
        }
        if ($this->testEnabled()) {
            $list[] = ['key' => 'test', 'name' => 'Test payment', 'description' => 'Development only — no real money moves'];
        }

        return $list;
    }

    public function get(string $key): PaymentGateway
    {
        return match (true) {
            $key === 'razorpay' && $this->razorpayConfigured() => app(RazorpayGateway::class),
            $key === 'test' && $this->testEnabled() => app(TestGateway::class),
            default => throw new ApiException('This payment method is not available.', 422, 'gateway_unavailable', ['gateway' => ['This payment method is not available.']]),
        };
    }

    public function testEnabled(): bool
    {
        return ! app()->isProduction() && (bool) config('payments.test.enabled');
    }

    private function razorpayConfigured(): bool
    {
        return filled(config('payments.razorpay.key_id')) && filled(config('payments.razorpay.key_secret'));
    }
}
