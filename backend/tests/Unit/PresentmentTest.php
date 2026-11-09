<?php

use App\Domain\Localization\Presentment;

it('converts store-currency minor units exactly, rounding half-up', function () {
    $usd = new Presentment('INR', 'USD', '0.012');

    expect($usd->convert(118_000))->toBe(1_416)   // ₹1,180.00 → $14.16
        ->and($usd->convert(12_345))->toBe(148)    // ₹123.45 → $1.4814 → $1.48
        ->and($usd->convert(12_500))->toBe(150)    // ₹125.00 → $1.50
        ->and((new Presentment('INR', 'USD', '0.0123'))->convert(118_000))->toBe(1_451) // $14.514 → $14.51
        ->and((new Presentment('INR', 'USD', '0.0125'))->convert(100))->toBe(1)  // $0.0125 → $0.01
        ->and((new Presentment('INR', 'USD', '0.015'))->convert(100))->toBe(2);  // $0.015 → $0.02 (half-up)
});

it('respects the target currency exponent', function () {
    expect((new Presentment('INR', 'JPY', '1.83'))->convert(100_000))->toBe(1_830); // ₹1,000 → ¥1,830
});

it('never loses precision on large amounts with high rates', function () {
    // ₹10 crore at 190 units per rupee would overflow integer maths with a scaled rate.
    expect((new Presentment('INR', 'USD', '190.123456789012'))->convert(1_000_000_000))->toBe(190_123_456_789);
});

it('gives the same share of a charged amount for refunds', function () {
    $usd = new Presentment('INR', 'USD', '0.012');

    expect($usd->share(100_000, 486_000, 5_832))->toBe(1_200) // 1,199.99… → 1,200
        ->and($usd->share(243_000, 486_000, 5_832))->toBe(2_916)
        ->and($usd->share(1, 0, 5_832))->toBe(0);
});
