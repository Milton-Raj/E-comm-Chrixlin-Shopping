<?php

namespace App\Domain\Reports\Export;

use Closure;

/**
 * One spreadsheet column: header, the row key it reads, how values are formatted,
 * and whether it gets a total. `tone` can mark individual cells good/bad/warn.
 */
final class Column
{
    public const TEXT = 'text';

    public const INT = 'int';

    public const MONEY = 'money';   // minor units in, formatted currency out

    public const PERCENT = 'percent'; // 0–1 fraction

    public const DATE = 'date';

    public const DATETIME = 'datetime';

    public const BOOL = 'bool';

    public const CODE = 'code';     // monospaced identifiers (SKU, order number)

    public const NUMBER = 'number'; // plain decimal (foreign amounts, exchange rates); value is a decimal string

    /** @param (Closure(array<string, mixed>): ?string)|null $tone returns 'good' | 'bad' | 'warn' | null */
    public function __construct(
        public readonly string $header,
        public readonly string $key,
        public readonly string $type = self::TEXT,
        public readonly float $width = 16,
        public readonly bool $total = false,
        public readonly ?Closure $tone = null,
        public readonly int $decimals = 2,
    ) {}

    public static function text(string $header, string $key, float $width = 22): self
    {
        return new self($header, $key, self::TEXT, $width);
    }

    public static function code(string $header, string $key, float $width = 18): self
    {
        return new self($header, $key, self::CODE, $width);
    }

    public static function int(string $header, string $key, bool $total = false, float $width = 12, ?Closure $tone = null): self
    {
        return new self($header, $key, self::INT, $width, $total, $tone);
    }

    public static function money(string $header, string $key, bool $total = true, float $width = 16): self
    {
        return new self($header, $key, self::MONEY, $width, $total);
    }

    public static function percent(string $header, string $key, float $width = 12): self
    {
        return new self($header, $key, self::PERCENT, $width);
    }

    public static function date(string $header, string $key, float $width = 14): self
    {
        return new self($header, $key, self::DATE, $width);
    }

    public static function datetime(string $header, string $key, float $width = 19): self
    {
        return new self($header, $key, self::DATETIME, $width);
    }

    /** A decimal number shown with a fixed number of places, e.g. an amount in the customer's currency or a rate. */
    public static function number(string $header, string $key, int $decimals = 2, float $width = 14): self
    {
        return new self($header, $key, self::NUMBER, $width, false, null, $decimals);
    }

    public static function bool(string $header, string $key, float $width = 10): self
    {
        return new self($header, $key, self::BOOL, $width);
    }

    /** @param Closure(array<string, mixed>): ?string $tone */
    public function withTone(Closure $tone): self
    {
        return new self($this->header, $this->key, $this->type, $this->width, $this->total, $tone, $this->decimals);
    }
}
