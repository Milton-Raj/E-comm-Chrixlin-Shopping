<?php

namespace App\Domain\Identity;

use Illuminate\Support\Facades\DB;

/**
 * CUS-{000001} from a single locked sequence row, so admin URLs read /admin/customers/CUS-000042
 * instead of a UUID. Runs in its own (nested) transaction so the lock is held until the user row is written.
 */
class CustomerNumberGenerator
{
    public function next(): string
    {
        return DB::transaction(function () {
            DB::table('customer_number_sequences')->insertOrIgnore(['id' => 1, 'last_value' => 0]);
            $row = DB::table('customer_number_sequences')->where('id', 1)->lockForUpdate()->first();
            $value = (int) $row->last_value + 1;
            DB::table('customer_number_sequences')->where('id', 1)->update(['last_value' => $value]);

            return sprintf('CUS-%06d', $value);
        });
    }
}
