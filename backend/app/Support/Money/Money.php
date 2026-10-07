<?php

namespace App\Support\Money;

use InvalidArgumentException;
use JsonSerializable;
use NumberFormatter;

/**
 * Immutable money value in integer minor units (PRD §84, ARCHITECTURE §6.4).
 * Never construct from floats. Rounding is half-up (away from zero).
 */
final class Money implements JsonSerializable
{
    private function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {}

    public static function of(int $minorUnits, string $currency): self
    {
        $currency = strtoupper($currency);
        self::exponent($currency); // validates the currency is configured

        return new self($minorUnits, $currency);
    }

    public static function zero(string $currency): self
    {
        return self::of(0, $currency);
    }

    public static function exponent(string $currency): int
    {
        $exponent = config('commerce.currencies.'.strtoupper($currency));

        if (! is_int($exponent)) {
            throw new InvalidArgumentException("Unsupported currency [{$currency}].");
        }

        return $exponent;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    /**
     * A percentage of this amount, given in basis points (1800 = 18.00%), rounded half-up.
     */
    public function percentage(int $basisPoints): self
    {
        return new self(self::divideRounded($this->amount * $basisPoints, 10_000), $this->currency);
    }

    /**
     * Split into parts proportional to $ratios without losing a minor unit:
     * remainders go to the earliest parts (largest-remainder by order).
     *
     * @param  list<int>  $ratios
     * @return list<self>
     */
    public function allocate(array $ratios): array
    {
        $total = array_sum($ratios);

        if ($ratios === [] || $total <= 0 || min($ratios) < 0) {
            throw new InvalidArgumentException('Ratios must be non-negative and sum to more than zero.');
        }

        $parts = [];
        $allocated = 0;

        foreach ($ratios as $ratio) {
            $share = intdiv($this->amount * $ratio, $total);
            $parts[] = $share;
            $allocated += $share;
        }

        $remainder = $this->amount - $allocated;
        $step = $remainder >= 0 ? 1 : -1;

        for ($i = 0; $remainder !== 0; $i = ($i + 1) % count($parts)) {
            if ($ratios[$i] === 0) {
                continue;
            }
            $parts[$i] += $step;
            $remainder -= $step;
        }

        return array_map(fn (int $minor) => new self($minor, $this->currency), $parts);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    public function format(?string $locale = null): string
    {
        $locale ??= (string) config('commerce.store.locale', 'en');
        $exponent = self::exponent($this->currency);
        $decimal = $this->amount / (10 ** $exponent); // display only, never used for arithmetic

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $exponent);
            $formatted = $formatter->formatCurrency($decimal, $this->currency);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return $this->currency.' '.number_format($decimal, $exponent);
    }

    /**
     * @return array{amount: int, currency: string, formatted: string}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted' => $this->format(),
        ];
    }

    /**
     * @return array{amount: int, currency: string, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** Integer division rounded half away from zero. */
    private static function divideRounded(int $numerator, int $denominator): int
    {
        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        if (abs($remainder) * 2 >= $denominator) {
            $quotient += $numerator >= 0 ? 1 : -1;
        }

        return $quotient;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatchException("Cannot combine {$this->currency} with {$other->currency}.");
        }
    }
}
