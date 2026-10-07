<?php

namespace App\Domain\Orders;

use Illuminate\Support\Facades\DB;

/**
 * ORD-{YYYY}-{000001} from a locked per-year sequence row (PRD §20). Call inside the order transaction.
 */
class OrderNumberGenerator
{
    public function next(): string
    {
        $year = (int) now()->format('Y');
        DB::table('order_number_sequences')->insertOrIgnore(['year' => $year, 'last_value' => 0]);
        $row = DB::table('order_number_sequences')->where('year', $year)->lockForUpdate()->first();
        $value = (int) $row->last_value + 1;
        DB::table('order_number_sequences')->where('year', $year)->update(['last_value' => $value]);

        return sprintf('ORD-%d-%06d', $year, $value);
    }
}
