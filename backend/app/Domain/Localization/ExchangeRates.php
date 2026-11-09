<?php

namespace App\Domain\Localization;

use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Daily market rates from the store currency (ARCHITECTURE D21). Rates are stored, never
 * fetched while a shopper waits; the owner's markup is applied on top when a rate is used.
 */
class ExchangeRates
{
    /** Decimal places kept for an effective rate. */
    private const SCALE = 12;

    public function __construct(private readonly InternationalSettings $international) {}

    /**
     * The conversion for a shopper's currency, or null when they pay in the store currency
     * (international selling off, currency not offered, or no rate stored yet).
     */
    public function presentmentFor(?string $currency): ?Presentment
    {
        $currency = strtoupper((string) $currency);
        $base = $this->international->storeCurrency();
        if ($currency === '' || $currency === $base || ! $this->international->enabled() || ! $this->international->offers($currency)) {
            return null;
        }

        $row = $this->stored($currency);

        return $row === null ? null : new Presentment($base, $currency, $this->withMarkup($row->rate), $row->rate, $this->international->markupBps(), $row->fetched_at);
    }

    /** Market rate plus markup, as a decimal string; null when no rate is stored. */
    public function effectiveRate(string $currency): ?string
    {
        $row = $this->stored($currency);

        return $row ? $this->withMarkup($row->rate) : null;
    }

    private function stored(string $currency): ?ExchangeRate
    {
        return ExchangeRate::query()->where('base_currency', $this->international->storeCurrency())->where('currency', strtoupper($currency))->first();
    }

    private function withMarkup(string $marketRate): string
    {
        return (string) BigDecimal::of($marketRate)
            ->multipliedBy(BigDecimal::of(10_000 + $this->international->markupBps())->withPointMovedLeft(4))
            ->toScale(self::SCALE, RoundingMode::HalfUp)
            ->strippedOfTrailingZeros();
    }

    /**
     * Offered currencies with their effective rate (only those with a stored rate).
     *
     * @return list<array{code: string, rate: string, exponent: int}>
     */
    public function offered(): array
    {
        $out = [];
        foreach ($this->international->currencies() as $code) {
            $rate = $this->effectiveRate($code);
            if ($rate !== null) {
                $out[] = ['code' => $code, 'rate' => $rate, 'exponent' => (int) config("commerce.currencies.{$code}")];
            }
        }

        return $out;
    }

    public function lastFetchedAt(): ?Carbon
    {
        $at = ExchangeRate::query()->where('base_currency', $this->international->storeCurrency())->max('fetched_at');

        return $at ? Carbon::parse($at) : null;
    }

    public function stale(): bool
    {
        $at = $this->lastFetchedAt();

        return $at === null || $at->lt(now()->subHours((int) config('localization.exchange_rates.stale_after_hours', 48)));
    }

    /**
     * Fetches today's rates for every offerable currency and stores them.
     *
     * @return int number of currencies updated
     */
    public function refresh(): int
    {
        $base = $this->international->storeCurrency();
        $url = str_replace('{base}', $base, (string) config('localization.exchange_rates.url'));
        $response = Http::timeout(15)->acceptJson()->get($url);

        $rates = (array) $response->json('rates', []);
        if (! $response->successful() || $response->json('result', 'success') !== 'success' || $rates === []) {
            Log::channel('orders')->warning('Exchange rate fetch failed.', ['status' => $response->status()]);
            throw new RuntimeException('The exchange rate service did not return rates (HTTP '.$response->status().').');
        }

        $updated = 0;
        foreach ((array) config('localization.offerable_currencies') as $code) {
            $value = $rates[$code] ?? null;
            if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
                continue;
            }
            $rate = BigDecimal::of((string) $value)->toScale(self::SCALE, RoundingMode::HalfUp);
            if (! $rate->isPositive()) {
                continue;
            }
            $values = ['rate' => (string) $rate, 'source' => 'open.er-api.com', 'fetched_at' => now()];
            ExchangeRate::query()->updateOrCreate(['base_currency' => $base, 'currency' => $code], $values);
            // The audit trail keeps every fetch, so any past order's rate can be traced to its source.
            ExchangeRateHistory::query()->create(['base_currency' => $base, 'currency' => $code, ...$values]);
            $updated++;
        }

        Log::channel('orders')->info('Exchange rates updated.', ['base' => $base, 'currencies' => $updated]);

        return $updated;
    }
}
