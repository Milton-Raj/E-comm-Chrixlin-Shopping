<?php

namespace App\Domain\Invoices;

/** Indian-style amount in words (lakh, crore), as printed on GST invoices. */
final class AmountInWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
        'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    /** @param int $minor amount in paise */
    public static function rupees(int $minor): string
    {
        $rupees = intdiv($minor, 100);
        $paise = $minor % 100;
        $words = 'Rupees '.($rupees === 0 ? 'Zero' : self::indian($rupees));
        if ($paise > 0) {
            $words .= ' and '.self::belowHundred($paise).' Paise';
        }

        return $words.' Only';
    }

    private static function indian(int $n): string
    {
        $parts = [];
        foreach ([[10_000_000, 'Crore'], [100_000, 'Lakh'], [1_000, 'Thousand'], [100, 'Hundred']] as [$size, $name]) {
            if ($n >= $size) {
                $count = intdiv($n, $size);
                $parts[] = ($size === 10_000_000 ? self::indian($count) : self::belowHundred($count)).' '.$name;
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = self::belowHundred($n);
        }

        return implode(' ', $parts);
    }

    private static function belowHundred(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n];
        }

        return trim(self::TENS[intdiv($n, 10)].' '.self::ONES[$n % 10]);
    }
}
