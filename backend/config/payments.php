<?php

/*
| Payment providers (ARCHITECTURE §6.11). Secrets come from the environment only.
*/

return [

    'test' => [
        // Development gateway; GatewayManager refuses it in production regardless of this flag.
        'enabled' => (bool) env('PAYMENT_TEST_GATEWAY', true),
        'webhook_secret' => env('PAYMENT_TEST_WEBHOOK_SECRET', 'local-test-webhook-secret'),
    ],

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

];
