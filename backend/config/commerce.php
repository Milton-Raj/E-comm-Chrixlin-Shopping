<?php

/*
| Store-level defaults. Values editable by admins live in the `settings` table
| and override these at runtime (App\Support\Settings\Settings).
*/

return [

    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/'),

    'store' => [
        'name' => env('STORE_NAME', env('APP_NAME', 'Chrixlin')),
        'currency' => env('STORE_CURRENCY', 'INR'),
        // Where the store ships from; delivery is estimated for this country until the shopper gives an address.
        'country' => env('STORE_COUNTRY', 'IN'),
        'locale' => env('STORE_LOCALE', 'en-IN'),
        // Wall-clock zone for documents people read (exports); the app itself stores UTC.
        'timezone' => env('STORE_TIMEZONE', 'Asia/Kolkata'),
    ],

    // Defaults for Admin → Settings → Marketing (stored values override these).
    'marketing' => [
        'low_stock_count' => 5,
        'whatsapp_number' => null,
        'gift_wrap_enabled' => false,
        'gift_wrap_price' => 9_900,
        'abandoned_cart_enabled' => false,
        'abandoned_cart_delay_hours' => 3,
        'review_request_enabled' => true,
        'review_request_delay_days' => 4,
    ],

    // ISO-4217 code => minor-unit exponent. Never assume 2 decimals.
    'currencies' => [
        'INR' => 2, 'USD' => 2, 'EUR' => 2, 'GBP' => 2, 'AED' => 2, 'AUD' => 2, 'CAD' => 2, 'SGD' => 2,
        'CHF' => 2, 'JPY' => 0, 'NZD' => 2, 'SAR' => 2, 'QAR' => 2, 'MYR' => 2, 'HKD' => 2, 'SEK' => 2,
        'NOK' => 2, 'DKK' => 2, 'ZAR' => 2, 'THB' => 2, 'LKR' => 2, 'NPR' => 2, 'BDT' => 2,
    ],

    // Symbols used where no locale formatter is available (e.g. Excel number formats).
    'currency_symbols' => [
        'INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'AED' => 'AED ', 'AUD' => 'A$', 'CAD' => 'C$', 'SGD' => 'S$',
        'CHF' => 'CHF ', 'JPY' => '¥', 'NZD' => 'NZ$', 'SAR' => 'SAR ', 'QAR' => 'QAR ', 'MYR' => 'RM', 'HKD' => 'HK$', 'SEK' => 'SEK ',
        'NOK' => 'NOK ', 'DKK' => 'DKK ', 'ZAR' => 'R', 'THB' => '฿', 'LKR' => 'Rs ', 'NPR' => 'NPR ', 'BDT' => '৳',
    ],

    'security' => [
        // Idle timeout for admin API sessions (SECURITY.md §3).
        'admin_idle_minutes' => (int) env('ADMIN_IDLE_MINUTES', 30),
        // Local development convenience only: production ALWAYS requires staff 2FA
        // (enforced in EnsureAdminAccess regardless of this value).
        'admin_require_2fa' => (bool) env('ADMIN_REQUIRE_2FA', true),
        // Progressive lockout: failed logins per email within an hour.
        'login_lockout_attempts' => (int) env('LOGIN_LOCKOUT_ATTEMPTS', 10),
    ],

    'health_token' => env('HEALTH_TOKEN'),

    'revalidate' => [
        'url' => env('REVALIDATE_URL'),
        'secret' => env('REVALIDATE_SECRET'),
    ],

    // Local development only: fixed password for DemoSeeder accounts (random when empty).
    'demo_password' => env('DEMO_PASSWORD'),

];
