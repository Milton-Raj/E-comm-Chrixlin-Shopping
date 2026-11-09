<?php

namespace App\Domain\Localization;

use App\Support\Settings\Settings;

/**
 * Owner-editable international selling switches (Admin → Settings → International).
 * Defaults live in config/localization.php; nothing here is hard-coded policy.
 */
class InternationalSettings
{
    public function __construct(private readonly Settings $settings) {}

    /** Off: only the store's own country is delivered to and everything is priced in the store currency. */
    public function enabled(): bool
    {
        return (bool) $this->settings->get('international.enabled', config('localization.defaults.enabled'));
    }

    public function storeCurrency(): string
    {
        return strtoupper((string) $this->settings->get('store.currency'));
    }

    public function storeCountry(): string
    {
        return strtoupper((string) config('commerce.store.country'));
    }

    /**
     * Currencies offered to shoppers (never the store currency, which is always offered).
     *
     * @return list<string>
     */
    public function currencies(): array
    {
        $chosen = (array) $this->settings->get('international.currencies', config('localization.defaults.currencies'));

        return array_values(array_filter(
            (array) config('localization.offerable_currencies'),
            fn (string $code) => in_array($code, $chosen, true) && $code !== $this->storeCurrency(),
        ));
    }

    public function offers(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->currencies(), true);
    }

    /** For shoppers whose own currency is not offered; the store currency when that one is off too. */
    public function fallbackCurrency(): string
    {
        $fallback = strtoupper((string) $this->settings->get('international.fallback_currency', config('localization.defaults.fallback_currency')));

        return $this->offers($fallback) ? $fallback : $this->storeCurrency();
    }

    /** Added to the market rate, in basis points (0–1000). */
    public function markupBps(): int
    {
        return min(1000, max(0, (int) $this->settings->get('international.markup_bps', config('localization.defaults.markup_bps'))));
    }

    public function unavailableMessage(): string
    {
        $message = trim((string) $this->settings->get('international.unavailable_message', ''));

        return $message !== '' ? $message : (string) config('localization.defaults.unavailable_message');
    }

    /** Whether orders may be delivered to this country. */
    public function deliversTo(?string $countryCode): bool
    {
        return $this->enabled() || strtoupper((string) $countryCode) === $this->storeCountry();
    }

    /** @return array{enabled: bool, currencies: list<string>, fallback_currency: string, markup_bps: int, unavailable_message: string} */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'currencies' => $this->currencies(),
            'fallback_currency' => $this->fallbackCurrency(),
            'markup_bps' => $this->markupBps(),
            'unavailable_message' => $this->unavailableMessage(),
        ];
    }
}
