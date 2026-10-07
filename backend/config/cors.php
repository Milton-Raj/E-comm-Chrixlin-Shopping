<?php

/*
| Only the storefront origin may call the API with credentials (SECURITY.md §6).
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:3000'))),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN', 'X-Request-ID', 'Idempotency-Key', 'X-Order-Token'],

    'exposed_headers' => ['X-Request-ID', 'Retry-After', 'Content-Disposition'],

    'max_age' => 600,

    'supports_credentials' => true,

];
