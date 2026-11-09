<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Localization\ExchangeRates;
use App\Domain\Localization\GeoLocator;
use App\Domain\Localization\InternationalSettings;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Support\Audit\Audit;
use App\Support\Http\ApiResponse;
use App\Support\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/** Admin → Settings → International: selling abroad, currencies, markup and today's rates. */
class InternationalController extends Controller
{
    public function __construct(private readonly InternationalSettings $international, private readonly ExchangeRates $rates) {}

    public function show(Request $request, GeoLocator $geo): JsonResponse
    {
        return ApiResponse::success($this->payload($request, $geo));
    }

    public function update(Request $request, Settings $settings, GeoLocator $geo): JsonResponse
    {
        $offerable = (array) config('localization.offerable_currencies');
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'currencies' => ['present', 'array', 'max:'.count($offerable)],
            'currencies.*' => ['string', Rule::in($offerable)],
            'fallback_currency' => ['required', 'string', Rule::in($offerable)],
            'markup_bps' => ['required', 'integer', 'min:0', 'max:1000'],
            'unavailable_message' => ['required', 'string', 'max:240'],
        ]);
        $data['currencies'] = array_values(array_unique($data['currencies']));
        if ($data['enabled'] && $data['currencies'] === []) {
            throw new ApiException('Choose at least one currency to sell in.', 422, null, ['currencies' => ['Choose at least one currency to sell in.']]);
        }
        if (! in_array($data['fallback_currency'], $data['currencies'], true) && $data['currencies'] !== []) {
            throw new ApiException('The fallback currency must be one you sell in.', 422, null, ['fallback_currency' => ['The fallback currency must be one you sell in.']]);
        }

        $before = $this->international->toArray();
        foreach (['enabled', 'currencies', 'fallback_currency', 'markup_bps'] as $key) {
            $settings->set("international.{$key}", $data[$key], $request->user()->getKey());
        }
        $settings->set('international.unavailable_message', trim($data['unavailable_message']), $request->user()->getKey());
        Audit::record('settings.international_updated', null, $before, $this->international->toArray(), $request->user());

        // First switch-on: fetch rates straight away rather than waiting for the schedule.
        $message = 'International selling settings saved.';
        if ($data['enabled'] && $this->rates->lastFetchedAt() === null) {
            try {
                $this->rates->refresh();
            } catch (Throwable) {
                $message .= ' Exchange rates could not be fetched yet — shoppers abroad see rupee prices until they are.';
            }
        }

        return ApiResponse::success($this->payload($request, $geo), $message);
    }

    public function refreshRates(Request $request, GeoLocator $geo): JsonResponse
    {
        try {
            $count = $this->rates->refresh();
        } catch (Throwable $e) {
            throw new ApiException('Could not fetch exchange rates right now. Please try again later.', 502, 'rates_unavailable');
        }
        Audit::record('settings.exchange_rates_refreshed', null, null, ['currencies' => $count], $request->user());

        return ApiResponse::success($this->payload($request, $geo), "Updated {$count} exchange rates.");
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, GeoLocator $geo): array
    {
        $base = $this->international->storeCurrency();
        $stored = ExchangeRate::query()->where('base_currency', $base)->get()->keyBy('currency');

        return [
            'settings' => $this->international->toArray(),
            'store_currency' => $base,
            'currencies' => array_map(fn (string $code) => [
                'code' => $code,
                'market_rate' => isset($stored[$code]) ? rtrim(rtrim((string) $stored[$code]->rate, '0'), '.') : null,
                'rate' => $this->rates->effectiveRate($code),
                'exponent' => (int) config("commerce.currencies.{$code}"),
            ], (array) config('localization.offerable_currencies')),
            'rates_updated_at' => $this->rates->lastFetchedAt()?->toIso8601String(),
            'rates_stale' => $this->rates->stale(),
            'geoip' => [
                'ready' => $geo->databaseReady(),
                'updated_at' => ($at = $geo->databaseUpdatedAt()) ? now()->setTimestamp($at)->toIso8601String() : null,
                // How the store sees whoever is looking at this page: proof the lookup works behind the CDN.
                'your_country' => $geo->countryFor($request),
            ],
        ];
    }
}
