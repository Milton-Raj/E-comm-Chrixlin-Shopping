<?php

namespace App\Console\Commands;

use App\Domain\Localization\GeoIpDatabase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/** Downloads the monthly DB-IP country database (also from Admin → Settings → International). */
#[Signature('app:geoip-update {--if-stale : Only download when the file is missing or older than 32 days}')]
#[Description('Download the IP-to-country database (DB-IP Lite, CC BY 4.0)')]
class UpdateGeoIpDatabase extends Command
{
    public function handle(GeoIpDatabase $database): int
    {
        if ($this->option('if-stale') && $database->fresh()) {
            return self::SUCCESS;
        }

        try {
            $month = $database->update();
        } catch (RuntimeException $e) {
            $this->error('Could not install the IP country database: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info("IP country database updated ({$month}).");

        return self::SUCCESS;
    }
}
