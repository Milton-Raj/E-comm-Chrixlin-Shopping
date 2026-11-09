<?php

namespace App\Domain\Localization;

use Illuminate\Http\Request;
use MaxMind\Db\Reader;
use Throwable;

/**
 * Visitor country from a trusted CDN header or a local IP-to-country database.
 * Lookups never leave the server; an unknown country is null (shoppers can still choose).
 */
class GeoLocator
{
    private ?Reader $reader = null;

    public function countryFor(Request $request): ?string
    {
        $fake = config('localization.geoip.fake_country');
        if (! app()->isProduction() && is_string($fake) && preg_match('/^[A-Za-z]{2}$/', $fake)) {
            return strtoupper($fake);
        }

        $header = config('localization.geoip.country_header');
        if (is_string($header) && $header !== '') {
            $value = strtoupper((string) $request->header($header));
            if (preg_match('/^[A-Z]{2}$/', $value) && $value !== 'XX' && $value !== 'T1') {
                return $value;
            }
        }

        return $this->lookup((string) $request->ip());
    }

    public function lookup(string $ip): ?string
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        try {
            $record = $this->reader()?->get($ip);
        } catch (Throwable) {
            return null;
        }
        $code = is_array($record) ? ($record['country']['iso_code'] ?? null) : null;

        return is_string($code) && preg_match('/^[A-Z]{2}$/', $code) ? $code : null;
    }

    public function databaseReady(): bool
    {
        return is_file((string) config('localization.geoip.path'));
    }

    public function databaseUpdatedAt(): ?int
    {
        $path = (string) config('localization.geoip.path');

        return is_file($path) ? (int) filemtime($path) : null;
    }

    private function reader(): ?Reader
    {
        if ($this->reader === null && $this->databaseReady()) {
            $this->reader = new Reader((string) config('localization.geoip.path'));
        }

        return $this->reader;
    }
}
