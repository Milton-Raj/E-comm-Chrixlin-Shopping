<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Page;
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

        $sample = "[Sample content — replace with your own policy before launch.]\n\n";
        $pages = [
            'contact' => ['Contact us', "We'd love to hear from you.\n\nEmail: care@example.test\nPhone: +91 00000 00000 (Mon–Sat, 10am–7pm IST)\n\nFor order questions, please include your order number."],
            'shipping' => ['Shipping policy', "Orders are dispatched within 1–2 working days.\n\nStandard delivery within India takes 3–5 working days and is complimentary on orders over ₹5,000. Express delivery takes 1–2 working days.\n\nDigital products are delivered instantly to your account and by email."],
            'returns' => ['Returns', "Unused physical items in original packaging can be returned within 14 days of delivery.\n\nTo start a return, contact us with your order number."],
            'refund-policy' => ['Refund policy', "Refunds are issued to the original payment method within 5–7 working days of approval.\n\nDigital products: refund eligibility depends on whether the item has been downloaded and on applicable law."],
            'privacy' => ['Privacy policy', 'We collect only the information needed to process orders, deliver products and support you. We never sell personal data.'],
            'terms' => ['Terms & conditions', 'By using this store you agree to these terms. Prices include applicable taxes unless stated otherwise.'],
            'faq' => ['Frequently asked questions', "How long does delivery take?\nStandard delivery in India takes 3–5 working days.\n\nHow do I download a digital product?\nAfter payment, open Account → Downloads, or use the link in your confirmation email.\n\nCan I change my order?\nContact us as soon as possible — we can change orders that have not shipped yet."],
        ];

        foreach ($pages as $slug => [$title, $body]) {
            Page::query()->firstOrCreate(['slug' => $slug], ['title' => $title, 'body' => $sample.$body, 'status' => 'published', 'published_at' => now()]);
        }
    }
}
