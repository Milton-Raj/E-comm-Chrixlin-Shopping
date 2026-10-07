<?php

namespace App\Domain\Tax;

/**
 * GST on tax-inclusive prices (ARCHITECTURE §6.6). Tax is extracted per line from
 * the discounted line amount and split CGST+SGST for intra-state supplies, IGST
 * otherwise. Rates are data (tax_classes); confirm with an accountant before launch.
 */
class TaxCalculator
{
    /**
     * Tax contained in a tax-inclusive amount, rounded half-up.
     */
    public function inclusiveTax(int $grossAmount, int $rateBps): int
    {
        if ($rateBps <= 0 || $grossAmount <= 0) {
            return 0;
        }

        $numerator = $grossAmount * $rateBps;
        $denominator = 10_000 + $rateBps;

        return intdiv($numerator * 2 + $denominator, $denominator * 2);
    }

    /**
     * @return array<string, int> component code => amount, e.g. ['CGST' => 90, 'SGST' => 90]
     */
    public function split(int $taxAmount, bool $intraState): array
    {
        if ($taxAmount === 0) {
            return [];
        }

        if (! $intraState) {
            return ['IGST' => $taxAmount];
        }

        $cgst = intdiv($taxAmount, 2);

        return ['CGST' => $cgst, 'SGST' => $taxAmount - $cgst];
    }
}
