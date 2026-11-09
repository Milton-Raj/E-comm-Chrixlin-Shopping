<?php

namespace App\Console\Commands;

use App\Domain\Localization\ExchangeRates;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/** Stores today's exchange rates (scheduled twice a day; also run from Settings → International). */
#[Signature('app:exchange-rates')]
#[Description('Fetch and store exchange rates from the store currency')]
class RefreshExchangeRates extends Command
{
    public function handle(ExchangeRates $rates): int
    {
        try {
            $count = $rates->refresh();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Updated {$count} exchange rates.");

        return self::SUCCESS;
    }
}
