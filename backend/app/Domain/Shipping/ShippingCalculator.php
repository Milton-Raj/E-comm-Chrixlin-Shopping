<?php

namespace App\Domain\Shipping;

use App\Models\ShippingMethod;
use Illuminate\Support\Collection;

/**
 * Zone + method + rate resolution (ARCHITECTURE §6.7). v1 zones: domestic (IN) and international.
 */
class ShippingCalculator
{
    public static function zoneFor(?string $countryCode): string
    {
        return strtoupper((string) $countryCode) === 'IN' ? 'domestic' : 'international';
    }

    /**
     * @return Collection<int, array{code: string, name: string, description: string|null, amount: int, days_min: int, days_max: int}>
     */
    public function optionsFor(?string $countryCode, int $merchandiseTotal): Collection
    {
        return ShippingMethod::query()
            ->where('zone', self::zoneFor($countryCode))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ShippingMethod $m) => [
                'code' => $m->code,
                'name' => $m->name,
                'description' => $m->description,
                'amount' => $m->free_over !== null && $merchandiseTotal >= $m->free_over ? 0 : $m->amount,
                'days_min' => $m->days_min,
                'days_max' => $m->days_max,
            ])
            ->values();
    }
}
