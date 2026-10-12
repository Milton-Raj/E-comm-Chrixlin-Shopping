<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Domain\Content\SiteContent;
use App\Domain\Marketing\MarketingSettings;
use App\Domain\Shipping\ShippingCalculator;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;

/** Owner-edited storefront text; keys missing here use the storefront's default copy. */
class SiteContentController extends Controller
{
    public function __invoke(SiteContent $content, MarketingSettings $marketing, ShippingCalculator $shipping): JsonResponse
    {
        $currency = (string) config('commerce.store.currency');

        return ApiResponse::success([
            // Domestic delivery methods for product pages (one method is shown there instead of a checkout choice).
            'delivery_options' => $shipping->optionsFor((string) config('commerce.store.country'), 0)
                ->map(fn (array $o) => [
                    'name' => $o['name'], 'days_min' => $o['days_min'], 'days_max' => $o['days_max'],
                    'amount' => Money::of($o['amount'], $currency)->toArray(),
                    'free_over' => $o['free_over'] !== null ? Money::of($o['free_over'], $currency)->toArray() : null,
                ])
                ->values(),
            'texts' => (object) $content->texts(),
            'editorial_image' => $content->editorialImageUrl(),
            'whatsapp_number' => $marketing->whatsappNumber(),
        ]);
    }
}
