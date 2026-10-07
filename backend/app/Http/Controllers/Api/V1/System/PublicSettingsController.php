<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Settings\Settings;
use Illuminate\Http\JsonResponse;

class PublicSettingsController extends Controller
{
    public function __invoke(Settings $settings): JsonResponse
    {
        return ApiResponse::success([
            'store' => [
                'name' => $settings->get('store.name'),
                'currency' => $settings->get('store.currency'),
                'locale' => $settings->get('store.locale'),
            ],
            'currencies' => array_map(
                fn (string $code, int $exponent) => ['code' => $code, 'exponent' => $exponent],
                array_keys((array) config('commerce.currencies')),
                array_values((array) config('commerce.currencies')),
            ),
            'payment_methods' => [], // Phase 5
            'features' => [
                'guest_checkout' => true,
            ],
        ]);
    }
}
