<?php

use App\Domain\Promotions\CouponService;
use App\Domain\Tax\TaxCalculator;
use App\Models\Coupon;

it('extracts GST from tax-inclusive prices, rounding half-up', function (int $gross, int $bps, int $tax) {
    expect((new TaxCalculator)->inclusiveTax($gross, $bps))->toBe($tax);
})->with([
    '₹1,180 at 18% contains ₹180' => [118_000, 1800, 18_000],
    '₹100 at 18%' => [10_000, 1800, 1_525],   // 1525.42 -> 1525
    '₹105 at 5%' => [10_500, 500, 500],
    'zero rate' => [10_000, 0, 0],
]);

it('splits intra-state tax into CGST + SGST without losing a paisa, IGST otherwise', function () {
    $calc = new TaxCalculator;
    expect($calc->split(1_525, true))->toBe(['CGST' => 762, 'SGST' => 763])
        ->and($calc->split(1_525, false))->toBe(['IGST' => 1_525])
        ->and($calc->split(0, true))->toBe([]);
});

it('calculates coupon discounts with caps and floors', function () {
    $service = new CouponService;
    $pct = new Coupon(['type' => 'percentage', 'value' => 1000, 'max_discount' => 20_000]);
    $fixed = new Coupon(['type' => 'fixed', 'value' => 50_000]);

    expect($service->merchandiseDiscount($pct, 100_000))->toBe(10_000)
        ->and($service->merchandiseDiscount($pct, 1_000_000))->toBe(20_000)     // capped
        ->and($service->merchandiseDiscount($fixed, 30_000))->toBe(30_000)      // never more than the order
        ->and($service->merchandiseDiscount(new Coupon(['type' => 'free_shipping', 'value' => 0]), 50_000))->toBe(0);
});
