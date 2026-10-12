<?php

namespace App\Domain\Marketing;

use App\Support\Settings\Settings;

/**
 * Owner-editable marketing switches (Admin → Settings → Marketing). Defaults live in
 * config/commerce.php under "marketing"; nothing here is hard-coded business policy.
 */
class MarketingSettings
{
    public function __construct(private readonly Settings $settings) {}

    /** Show "Only N left" when a product's available stock is at or below this (0 = never). */
    public function lowStockCount(): int
    {
        return max(0, (int) $this->settings->get('marketing.low_stock_count', 5));
    }

    /** Store WhatsApp number in international format without "+" (e.g. 919876543210), or null. */
    public function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->settings->get('marketing.whatsapp_number', ''));

        return $digits !== '' ? $digits : null;
    }

    public function giftWrapEnabled(): bool
    {
        return (bool) $this->settings->get('marketing.gift_wrap_enabled', false);
    }

    /** Gift wrapping charge per order, in minor units of the store currency. */
    public function giftWrapPrice(): int
    {
        return max(0, (int) $this->settings->get('marketing.gift_wrap_price', 0));
    }

    public function abandonedCartEnabled(): bool
    {
        return (bool) $this->settings->get('marketing.abandoned_cart_enabled', false);
    }

    /** Hours of bag inactivity before the single reminder email is sent. */
    public function abandonedCartDelayHours(): int
    {
        return min(72, max(1, (int) $this->settings->get('marketing.abandoned_cart_delay_hours', 3)));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'low_stock_count' => $this->lowStockCount(),
            'whatsapp_number' => $this->whatsappNumber(),
            'gift_wrap_enabled' => $this->giftWrapEnabled(),
            'gift_wrap_price' => $this->giftWrapPrice(),
            'abandoned_cart_enabled' => $this->abandonedCartEnabled(),
            'abandoned_cart_delay_hours' => $this->abandonedCartDelayHours(),
        ];
    }
}
