<?php

namespace App\Domain\Localization;

/** Which currency a shopper sees by default, from their country. */
class CurrencyResolver
{
    public function __construct(private readonly InternationalSettings $international) {}

    public function forCountry(?string $country): string
    {
        $base = $this->international->storeCurrency();
        $country = strtoupper((string) $country);
        if (! $this->international->enabled() || $country === '' || $country === $this->international->storeCountry()) {
            return $base;
        }

        $own = config("localization.country_currency.{$country}");

        return is_string($own) && $this->international->offers($own) ? $own : $this->international->fallbackCurrency();
    }
}
