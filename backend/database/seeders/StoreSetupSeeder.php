<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use App\Models\TaxClass;
use Illuminate\Database\Seeder;

/**
 * Baseline store configuration (all environments, idempotent). Rates are starting
 * values for India; confirm GST classes with an accountant and adjust in Admin → Settings.
 */
class StoreSetupSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['slug' => 'standard', 'name' => 'GST 18%', 'rate_bps' => 1800, 'is_default' => true],
            ['slug' => 'reduced', 'name' => 'GST 12%', 'rate_bps' => 1200, 'is_default' => false],
            ['slug' => 'low', 'name' => 'GST 5%', 'rate_bps' => 500, 'is_default' => false],
            ['slug' => 'zero', 'name' => 'Exempt', 'rate_bps' => 0, 'is_default' => false],
        ] as $class) {
            TaxClass::query()->firstOrCreate(['slug' => $class['slug']], $class);
        }

        foreach ([
            ['code' => 'standard', 'zone' => 'domestic', 'name' => 'Standard delivery', 'description' => 'Insured, 3–5 working days', 'amount' => 25_000, 'free_over' => null, 'days_min' => 3, 'days_max' => 5, 'sort_order' => 1],
            ['code' => 'express', 'zone' => 'domestic', 'name' => 'Express delivery', 'description' => 'Priority, 1–2 working days', 'amount' => 60_000, 'free_over' => null, 'days_min' => 1, 'days_max' => 2, 'sort_order' => 2],
            ['code' => 'standard', 'zone' => 'international', 'name' => 'International standard', 'description' => 'Tracked, 7–12 working days', 'amount' => 250_000, 'free_over' => null, 'days_min' => 7, 'days_max' => 12, 'sort_order' => 1],
            ['code' => 'express', 'zone' => 'international', 'name' => 'International express', 'description' => 'Courier, 3–5 working days', 'amount' => 450_000, 'free_over' => null, 'days_min' => 3, 'days_max' => 5, 'sort_order' => 2],
        ] as $method) {
            ShippingMethod::query()->firstOrCreate(['code' => $method['code'], 'zone' => $method['zone']], $method);
        }
    }
}
