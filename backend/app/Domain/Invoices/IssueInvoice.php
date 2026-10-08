<?php

namespace App\Domain\Invoices;

use App\Models\Invoice;
use App\Models\Order;
use App\Support\Settings\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues the GST tax invoice for a paid order — exactly once. Numbers run consecutively
 * within the Indian financial year (April–March) from a locked sequence row, so there are
 * no gaps or duplicates even under concurrent payments. Seller details are snapshotted.
 */
class IssueInvoice
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Order $order): Invoice
    {
        return DB::transaction(function () use ($order): Invoice {
            $existing = Invoice::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $issuedAt = now();
            $year = self::financialYear($issuedAt);
            DB::table('invoice_number_sequences')->insertOrIgnore(['financial_year' => $year, 'last_value' => 0]);
            $row = DB::table('invoice_number_sequences')->where('financial_year', $year)->lockForUpdate()->first();
            $sequence = (int) $row->last_value + 1;
            DB::table('invoice_number_sequences')->where('financial_year', $year)->update(['last_value' => $sequence]);

            return Invoice::query()->create([
                'order_id' => $order->id,
                'invoice_number' => self::number($year, $sequence),
                'financial_year' => $year,
                'sequence' => $sequence,
                'issued_at' => $issuedAt,
                'seller' => [
                    'legal_name' => (string) ($this->settings->get('store.legal_name') ?: $this->settings->get('store.name', config('commerce.store.name'))),
                    'gstin' => $this->settings->get('store.gstin') ?: null,
                    'address' => $this->settings->get('store.address') ?: null,
                    'state_code' => (string) $this->settings->get('store.state_code', 'TN'),
                    'email' => $this->settings->get('store.support_email') ?: null,
                ],
            ]);
        });
    }

    /** "2026-27" for any date from 1 April 2026 to 31 March 2027, in the store's time zone. */
    public static function financialYear(Carbon $at): string
    {
        $local = $at->copy()->setTimezone((string) config('commerce.store.timezone', 'UTC'));
        $start = $local->month >= 4 ? $local->year : $local->year - 1;

        return $start.'-'.Str::substr((string) ($start + 1), 2);
    }

    /** INV26-27/000001 — 15 characters, within GST's 16-character limit. */
    public static function number(string $financialYear, int $sequence): string
    {
        return sprintf('INV%s/%06d', Str::substr($financialYear, 2), $sequence);
    }
}
