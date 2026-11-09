<?php

namespace App\Domain\Orders;

use App\Support\Settings\Settings;

/** Owner switches for new-order alerts outside the admin (Admin → Settings → Order alerts). */
class OrderAlertSettings
{
    public function __construct(private readonly Settings $settings) {}

    public function whatsappEnabled(): bool
    {
        return (bool) $this->settings->get('alerts.whatsapp_enabled', false) && $this->whatsappNumber() !== null;
    }

    /** International number, digits only (e.g. 919876543210), or null. */
    public function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->settings->get('alerts.whatsapp_number', ''));

        return $digits !== '' ? $digits : null;
    }
}
