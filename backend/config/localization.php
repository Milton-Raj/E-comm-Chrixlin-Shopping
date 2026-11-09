<?php

/*
| International selling (Admin → Settings → International). The owner's choices live in
| the `settings` table (App\Domain\Localization\InternationalSettings); this file holds the
| defaults and the reference data they choose from.
*/

return [

    // Defaults for the owner's settings (stored values override these).
    'defaults' => [
        'enabled' => false,
        // Currencies offered to shoppers abroad; each must also be in commerce.currencies.
        'currencies' => ['USD', 'EUR', 'GBP', 'AED', 'AUD', 'CAD', 'SGD'],
        // Used when a shopper's own currency is not offered.
        'fallback_currency' => 'USD',
        // Added on top of the market rate to cover card conversion costs (basis points, 250 = 2.5%).
        'markup_bps' => 0,
        'unavailable_message' => 'International shipping is coming soon. Until then, we can only deliver within India.',
    ],

    // Currencies the owner can switch on. Two-decimal currencies plus JPY (no minor unit);
    // three-decimal currencies (KWD, BHD, OMR) are left out because card gateways round them.
    'offerable_currencies' => ['USD', 'EUR', 'GBP', 'AED', 'AUD', 'CAD', 'SGD', 'CHF', 'JPY', 'NZD', 'SAR', 'QAR', 'MYR', 'HKD', 'SEK', 'NOK', 'DKK', 'ZAR', 'THB', 'LKR', 'NPR', 'BDT'],

    'exchange_rates' => [
        // Daily market rates with the store currency as the base. {base} is replaced with it.
        // Default: ExchangeRate-API's open endpoint (no key; attribution in the storefront footer).
        'url' => env('FX_RATES_URL', 'https://open.er-api.com/v6/latest/{base}'),
        // A rate older than this is still used, but staff are warned (launch check, settings).
        'stale_after_hours' => 48,
    ],

    'geoip' => [
        // Country database read locally, so no visitor IP leaves the server. DB-IP "IP to
        // Country Lite" (CC BY 4.0): refreshed monthly by `php artisan app:geoip-update`.
        'path' => storage_path('app/geoip/country.mmdb'),
        'download_url' => env('GEOIP_DOWNLOAD_URL', 'https://download.db-ip.com/free/dbip-country-lite-{month}.mmdb.gz'),
        // Optional: a header set by a trusted CDN/proxy in front of the API (e.g. CF-IPCountry).
        'country_header' => env('GEOIP_COUNTRY_HEADER'),
        // Local development only: pretend every visitor is in this country (ignored in production).
        'fake_country' => env('GEOIP_FAKE_COUNTRY'),
    ],

    // ISO 3166-1 alpha-2 => ISO 4217 currency of that country (shoppers' default currency).
    'country_currency' => [
        'AD' => 'EUR', 'AE' => 'AED', 'AF' => 'AFN', 'AG' => 'XCD', 'AI' => 'XCD', 'AL' => 'ALL', 'AM' => 'AMD', 'AO' => 'AOA', 'AR' => 'ARS', 'AS' => 'USD',
        'AT' => 'EUR', 'AU' => 'AUD', 'AW' => 'AWG', 'AX' => 'EUR', 'AZ' => 'AZN', 'BA' => 'BAM', 'BB' => 'BBD', 'BD' => 'BDT', 'BE' => 'EUR', 'BF' => 'XOF',
        'BG' => 'BGN', 'BH' => 'BHD', 'BI' => 'BIF', 'BJ' => 'XOF', 'BL' => 'EUR', 'BM' => 'BMD', 'BN' => 'BND', 'BO' => 'BOB', 'BQ' => 'USD', 'BR' => 'BRL',
        'BS' => 'BSD', 'BT' => 'BTN', 'BW' => 'BWP', 'BY' => 'BYN', 'BZ' => 'BZD', 'CA' => 'CAD', 'CC' => 'AUD', 'CD' => 'CDF', 'CF' => 'XAF', 'CG' => 'XAF',
        'CH' => 'CHF', 'CI' => 'XOF', 'CK' => 'NZD', 'CL' => 'CLP', 'CM' => 'XAF', 'CN' => 'CNY', 'CO' => 'COP', 'CR' => 'CRC', 'CU' => 'CUP', 'CV' => 'CVE',
        'CW' => 'ANG', 'CX' => 'AUD', 'CY' => 'EUR', 'CZ' => 'CZK', 'DE' => 'EUR', 'DJ' => 'DJF', 'DK' => 'DKK', 'DM' => 'XCD', 'DO' => 'DOP', 'DZ' => 'DZD',
        'EC' => 'USD', 'EE' => 'EUR', 'EG' => 'EGP', 'ER' => 'ERN', 'ES' => 'EUR', 'ET' => 'ETB', 'FI' => 'EUR', 'FJ' => 'FJD', 'FK' => 'FKP', 'FM' => 'USD',
        'FO' => 'DKK', 'FR' => 'EUR', 'GA' => 'XAF', 'GB' => 'GBP', 'GD' => 'XCD', 'GE' => 'GEL', 'GF' => 'EUR', 'GG' => 'GBP', 'GH' => 'GHS', 'GI' => 'GIP',
        'GL' => 'DKK', 'GM' => 'GMD', 'GN' => 'GNF', 'GP' => 'EUR', 'GQ' => 'XAF', 'GR' => 'EUR', 'GT' => 'GTQ', 'GU' => 'USD', 'GW' => 'XOF', 'GY' => 'GYD',
        'HK' => 'HKD', 'HN' => 'HNL', 'HR' => 'EUR', 'HT' => 'HTG', 'HU' => 'HUF', 'ID' => 'IDR', 'IE' => 'EUR', 'IL' => 'ILS', 'IM' => 'GBP', 'IN' => 'INR',
        'IQ' => 'IQD', 'IR' => 'IRR', 'IS' => 'ISK', 'IT' => 'EUR', 'JE' => 'GBP', 'JM' => 'JMD', 'JO' => 'JOD', 'JP' => 'JPY', 'KE' => 'KES', 'KG' => 'KGS',
        'KH' => 'KHR', 'KI' => 'AUD', 'KM' => 'KMF', 'KN' => 'XCD', 'KP' => 'KPW', 'KR' => 'KRW', 'KW' => 'KWD', 'KY' => 'KYD', 'KZ' => 'KZT', 'LA' => 'LAK',
        'LB' => 'LBP', 'LC' => 'XCD', 'LI' => 'CHF', 'LK' => 'LKR', 'LR' => 'LRD', 'LS' => 'LSL', 'LT' => 'EUR', 'LU' => 'EUR', 'LV' => 'EUR', 'LY' => 'LYD',
        'MA' => 'MAD', 'MC' => 'EUR', 'MD' => 'MDL', 'ME' => 'EUR', 'MF' => 'EUR', 'MG' => 'MGA', 'MH' => 'USD', 'MK' => 'MKD', 'ML' => 'XOF', 'MM' => 'MMK',
        'MN' => 'MNT', 'MO' => 'MOP', 'MP' => 'USD', 'MQ' => 'EUR', 'MR' => 'MRU', 'MS' => 'XCD', 'MT' => 'EUR', 'MU' => 'MUR', 'MV' => 'MVR', 'MW' => 'MWK',
        'MX' => 'MXN', 'MY' => 'MYR', 'MZ' => 'MZN', 'NA' => 'NAD', 'NC' => 'XPF', 'NE' => 'XOF', 'NF' => 'AUD', 'NG' => 'NGN', 'NI' => 'NIO', 'NL' => 'EUR',
        'NO' => 'NOK', 'NP' => 'NPR', 'NR' => 'AUD', 'NU' => 'NZD', 'NZ' => 'NZD', 'OM' => 'OMR', 'PA' => 'PAB', 'PE' => 'PEN', 'PF' => 'XPF', 'PG' => 'PGK',
        'PH' => 'PHP', 'PK' => 'PKR', 'PL' => 'PLN', 'PM' => 'EUR', 'PN' => 'NZD', 'PR' => 'USD', 'PS' => 'ILS', 'PT' => 'EUR', 'PW' => 'USD', 'PY' => 'PYG',
        'QA' => 'QAR', 'RE' => 'EUR', 'RO' => 'RON', 'RS' => 'RSD', 'RU' => 'RUB', 'RW' => 'RWF', 'SA' => 'SAR', 'SB' => 'SBD', 'SC' => 'SCR', 'SD' => 'SDG',
        'SE' => 'SEK', 'SG' => 'SGD', 'SH' => 'SHP', 'SI' => 'EUR', 'SK' => 'EUR', 'SL' => 'SLE', 'SM' => 'EUR', 'SN' => 'XOF', 'SO' => 'SOS', 'SR' => 'SRD',
        'SS' => 'SSP', 'ST' => 'STN', 'SV' => 'USD', 'SX' => 'ANG', 'SY' => 'SYP', 'SZ' => 'SZL', 'TC' => 'USD', 'TD' => 'XAF', 'TG' => 'XOF', 'TH' => 'THB',
        'TJ' => 'TJS', 'TK' => 'NZD', 'TL' => 'USD', 'TM' => 'TMT', 'TN' => 'TND', 'TO' => 'TOP', 'TR' => 'TRY', 'TT' => 'TTD', 'TV' => 'AUD', 'TW' => 'TWD',
        'TZ' => 'TZS', 'UA' => 'UAH', 'UG' => 'UGX', 'US' => 'USD', 'UY' => 'UYU', 'UZ' => 'UZS', 'VA' => 'EUR', 'VC' => 'XCD', 'VE' => 'VES', 'VG' => 'USD',
        'VI' => 'USD', 'VN' => 'VND', 'VU' => 'VUV', 'WF' => 'XPF', 'WS' => 'WST', 'XK' => 'EUR', 'YE' => 'YER', 'YT' => 'EUR', 'ZA' => 'ZAR', 'ZM' => 'ZMW',
        'ZW' => 'USD',
    ],

];
