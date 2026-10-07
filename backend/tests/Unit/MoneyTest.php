<?php

use App\Support\Money\CurrencyMismatchException;
use App\Support\Money\Money;

it('adds and subtracts in minor units', function () {
    $a = Money::of(1999, 'INR');
    $b = Money::of(1, 'INR');

    expect($a->add($b)->amount)->toBe(2000)
        ->and($a->subtract($b)->amount)->toBe(1998)
        ->and($a->multiply(3)->amount)->toBe(5997);
});

it('is immutable', function () {
    $a = Money::of(100, 'INR');
    $a->add(Money::of(50, 'INR'));

    expect($a->amount)->toBe(100);
});

it('rounds percentages half-up', function (int $amount, int $bps, int $expected) {
    expect(Money::of($amount, 'INR')->percentage($bps)->amount)->toBe($expected);
})->with([
    '18% of 999' => [999, 1800, 180],   // 179.82 -> 180
    '18% of 25' => [25, 1800, 5],       // 4.5 -> 5
    '5% of 10' => [10, 500, 1],         // 0.5 -> 1
    '5% of 9' => [9, 500, 0],           // 0.45 -> 0
    'negative' => [-25, 1800, -5],      // symmetric for negatives
]);

it('allocates without losing a minor unit', function (int $amount, array $ratios, array $expected) {
    $parts = Money::of($amount, 'INR')->allocate($ratios);

    expect(array_map(fn (Money $m) => $m->amount, $parts))->toBe($expected)
        ->and(array_sum(array_map(fn (Money $m) => $m->amount, $parts)))->toBe($amount);
})->with([
    'equal thirds' => [100, [1, 1, 1], [34, 33, 33]],
    'weighted' => [1000, [3, 1], [750, 250]],
    'with zero ratio' => [101, [1, 0, 1], [51, 0, 50]],
    'negative' => [-100, [1, 1, 1], [-34, -33, -33]],
]);

it('refuses to combine different currencies', function () {
    Money::of(100, 'INR')->add(Money::of(100, 'USD'));
})->throws(CurrencyMismatchException::class);

it('rejects unconfigured currencies', function () {
    Money::of(100, 'XYZ');
})->throws(InvalidArgumentException::class);

it('formats using the currency exponent', function () {
    expect(Money::of(249900, 'INR')->format('en-IN'))->toBe('₹2,499.00')
        ->and(Money::of(1999, 'USD')->format('en-US'))->toBe('$19.99');
});

it('serialises to the API money shape', function () {
    expect(Money::of(1999, 'inr')->toArray())->toMatchArray(['amount' => 1999, 'currency' => 'INR']);
});
