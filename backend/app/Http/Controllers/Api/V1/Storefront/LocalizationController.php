<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Domain\Localization\CurrencyResolver;
use App\Domain\Localization\ExchangeRates;
use App\Domain\Localization\GeoLocator;
use App\Domain\Localization\InternationalSettings;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Where the shopper is and what they can pay in (ARCHITECTURE D21). Rates here are for
 * showing catalogue prices only; the bag and checkout are always converted server-side.
 */
class LocalizationController extends Controller
{
    public function __invoke(Request $request, InternationalSettings $international, ExchangeRates $rates, GeoLocator $geo, CurrencyResolver $resolver): JsonResponse
    {
        $country = $geo->countryFor($request);
        $base = $international->storeCurrency();
        $enabled = $international->enabled();
        $offered = $enabled ? $rates->offered() : [];
        $default = $resolver->forCountry($country);

        return ApiResponse::success([
            'enabled' => $enabled,
            'store_currency' => $base,
            'store_country' => $international->storeCountry(),
            'detected_country' => $country,
            // Only a currency with a stored rate can be the default.
            'default_currency' => in_array($default, array_column($offered, 'code'), true) ? $default : $base,
            'currencies' => [['code' => $base, 'rate' => '1', 'exponent' => (int) config("commerce.currencies.{$base}")], ...$offered],
            'countries' => $enabled ? array_keys((array) config('localization.country_currency')) : [$international->storeCountry()],
            'delivers_to_visitor' => $country === null || $international->deliversTo($country),
            'unavailable_message' => $international->unavailableMessage(),
            'rates_updated_at' => $enabled ? $rates->lastFetchedAt()?->toIso8601String() : null,
        ])->header('Cache-Control', 'private, no-store');
    }
}
