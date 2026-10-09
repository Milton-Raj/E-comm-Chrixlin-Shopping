<?php

/*
| Courier integration (ARCHITECTURE §6.13). Credentials come from the environment only.
| Shiprocket needs a dedicated "API user" (Shiprocket → Settings → API → Configure),
| not the main login.
*/

return [

    'shiprocket' => [
        'email' => env('SHIPROCKET_EMAIL'),
        'password' => env('SHIPROCKET_PASSWORD'),
        // Shared secret Shiprocket sends in the x-api-key header of tracking webhooks.
        'webhook_token' => env('SHIPROCKET_WEBHOOK_TOKEN'),
        // Nickname of the pickup address saved in Shiprocket → Settings → Pickup Addresses.
        'pickup_location' => env('SHIPROCKET_PICKUP_LOCATION', 'Primary'),
        // Shiprocket has no sandbox. In test mode only the (free) Shiprocket order is created:
        // no courier/AWB is assigned (which charges the wallet) and no pickup is requested.
        'test_mode' => (bool) env('SHIPROCKET_TEST_MODE', false),
        'base_url' => rtrim((string) env('SHIPROCKET_BASE_URL', 'https://apiv2.shiprocket.in/v1/external'), '/'),
        'tracking_url' => 'https://shiprocket.co/tracking/{awb}',
        // Parcel used when products have no weight; dimensions are per order.
        'package' => [
            'length_cm' => (float) env('SHIPROCKET_PACKAGE_LENGTH_CM', 30),
            'breadth_cm' => (float) env('SHIPROCKET_PACKAGE_BREADTH_CM', 25),
            'height_cm' => (float) env('SHIPROCKET_PACKAGE_HEIGHT_CM', 10),
            'default_weight_grams' => (int) env('SHIPROCKET_DEFAULT_WEIGHT_GRAMS', 500),
        ],
    ],

];
