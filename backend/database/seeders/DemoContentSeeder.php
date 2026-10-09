<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

/**
 * Demo coupons and content pages (development only). Page text is clearly marked as
 * sample content: real policies must be written for the business and reviewed (ARCHITECTURE R10).
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'WELCOME10', 'description' => '10% off your first order (up to ₹2,000)', 'type' => 'percentage', 'value' => 1000, 'max_discount' => 200_000, 'first_order_only' => true, 'per_customer_limit' => 1],
            ['code' => 'FLAT500', 'description' => '₹500 off orders over ₹5,000', 'type' => 'fixed', 'value' => 50_000, 'min_order_total' => 500_000],
            ['code' => 'FREESHIP', 'description' => 'Free delivery on any order', 'type' => 'free_shipping', 'value' => 0],
            ['code' => 'DIGITAL20', 'description' => '20% off (up to ₹1,000)', 'type' => 'percentage', 'value' => 2000, 'max_discount' => 100_000, 'usage_limit' => 100],
            ['code' => 'EXPIRED15', 'description' => 'An expired code, for testing', 'type' => 'percentage', 'value' => 1500, 'ends_at' => now()->subDay()],
        ] as $coupon) {
            Coupon::query()->firstOrCreate(['code' => $coupon['code']], $coupon + ['is_active' => true]);
        }

        // Footer pages come from StorePagesSeeder (all environments).
    }
}
