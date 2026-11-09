<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MaxMind\Db\Reader;
use Throwable;

/**
 * Downloads the monthly DB-IP country database used to pick a visitor's currency.
 * The new file is validated before it replaces the old one, so a bad download never
 * breaks lookups.
 */
#[Signature('app:geoip-update {--if-stale : Only download when the file is missing or older than 32 days}')]
#[Description('Download the IP-to-country database (DB-IP Lite, CC BY 4.0)')]
class UpdateGeoIpDatabase extends Command
{
    public function handle(): int
    {
        $path = (string) config('localization.geoip.path');
        if ($this->option('if-stale') && is_file($path) && filemtime($path) > now()->subDays(32)->timestamp) {
            return self::SUCCESS;
        }

        // A new month's file appears in its first days; fall back to last month's until then.
        foreach ([now(), now()->subMonthNoOverflow()] as $month) {
            $url = str_replace('{month}', $month->format('Y-m'), (string) config('localization.geoip.download_url'));
            try {
                $response = Http::timeout(120)->get($url);
            } catch (Throwable) {
                continue;
            }
            if (! $response->successful()) {
                continue;
            }

            $data = str_ends_with($url, '.gz') ? @gzdecode($response->body()) : $response->body();
            if (! is_string($data) || $data === '') {
                continue;
            }

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            $tmp = $path.'.download';
            file_put_contents($tmp, $data);
            try {
                (new Reader($tmp))->close(); // throws when the file is not a valid database
            } catch (Throwable) {
                @unlink($tmp);

                continue;
            }
            rename($tmp, $path);

            Log::channel('orders')->info('IP country database updated.', ['month' => $month->format('Y-m')]);
            $this->info('IP country database updated ('.$month->format('Y-m').').');

            return self::SUCCESS;
        }

        Log::channel('orders')->warning('IP country database download failed.');
        $this->error('Could not download the IP country database.');

        return self::FAILURE;
    }
}
