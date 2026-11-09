<?php

namespace App\Domain\Localization;

use App\Support\Money\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

/**
 * Store-currency amounts shown and charged in the shopper's currency at one locked rate.
 * Every conversion is exact decimal arithmetic rounded half-up to the target minor unit,
 * so the same rate always gives the same figures (cart, order, email, refund).
 */
final class Presentment
{
    public function __construct(
        public readonly string $baseCurrency,
        public readonly string $currency,
        /** Units of $currency per 1 unit of $baseCurrency, markup included (decimal string). */
        public readonly string $rate,
        /** Audit detail behind $rate: the market rate, the owner's markup and when that rate was fetched. */
        public readonly ?string $marketRate = null,
        public readonly ?int $markupBps = null,
        public readonly ?Carbon $fetchedAt = null,
    ) {}

    public function convert(int $baseMinor): int
    {
        return BigDecimal::of($baseMinor)
            ->withPointMovedLeft(Money::exponent($this->baseCurrency))
            ->multipliedBy($this->rate)
            ->withPointMovedRight(Money::exponent($this->currency))
            ->toScale(0, RoundingMode::HalfUp)
            ->toInt();
    }

    /** Store-currency amount that corresponds to a share of a charged amount (refunds). */
    public function share(int $baseMinor, int $baseTotal, int $chargedTotal): int
    {
        if ($baseTotal <= 0) {
            return 0;
        }

        return BigDecimal::of($chargedTotal)->multipliedBy($baseMinor)->dividedBy($baseTotal, 0, RoundingMode::HalfUp)->toInt();
    }

    public function money(int $baseMinor): Money
    {
        return Money::of($this->convert($baseMinor), $this->currency);
    }

    /** @return array{currency: string, rate: string} */
    public function toArray(): array
    {
        return ['currency' => $this->currency, 'rate' => $this->rate];
    }
}
