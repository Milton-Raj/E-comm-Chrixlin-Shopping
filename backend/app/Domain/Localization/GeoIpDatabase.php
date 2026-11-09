<?php

namespace App\Domain\Localization;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MaxMind\Db\Reader;
use RuntimeException;
use Throwable;

/**
 * Installs the monthly DB-IP country database used to pick a visitor's currency. The file
 * is streamed to disk and unpacked in chunks (shared-hosting memory), validated, then swapped
 * in, so a bad download never breaks lookups. Failures say exactly what went wrong.
 */
class GeoIpDatabase
{
    public function path(): string
    {
        return (string) config('localization.geoip.path');
    }

    public function fresh(): bool
    {
        return is_file($this->path()) && filemtime($this->path()) > now()->subDays(32)->timestamp;
    }

    /**
     * @return string the month installed ("2026-11")
     *
     * @throws RuntimeException with the reason for each month tried
     */
    public function update(): string
    {
        $path = $this->path();
        if (! is_dir(dirname($path)) && ! @mkdir(dirname($path), 0755, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('Cannot create the folder '.dirname($path).' (check storage permissions).');
        }

        $reasons = [];
        // A new month's file appears in its first days; fall back to last month's until then.
        foreach ([now(), now()->subMonthNoOverflow()] as $month) {
            $label = $month->format('Y-m');
            $url = str_replace('{month}', $label, (string) config('localization.geoip.download_url'));
            $download = $path.'.download.gz';
            $unpacked = $path.'.download';
            try {
                $response = Http::timeout(180)->withUserAgent('Mozilla/5.0 (compatible; StoreGeoUpdate/1.0)')->sink($download)->get($url);
                if (! $response->successful()) {
                    $reasons[] = "{$label}: the download server answered HTTP {$response->status()}";

                    continue;
                }
                $this->unpack($download, $unpacked, str_ends_with($url, '.gz'));
                (new Reader($unpacked))->close(); // throws when the file is not a valid database
                if (! rename($unpacked, $path)) {
                    throw new RuntimeException('could not replace '.$path);
                }
                Log::channel('orders')->info('IP country database updated.', ['month' => $label]);

                return $label;
            } catch (Throwable $e) {
                $reasons[] = "{$label}: ".$e->getMessage();
            } finally {
                @unlink($download);
                @unlink($unpacked);
            }
        }

        Log::channel('orders')->warning('IP country database download failed.', ['reasons' => $reasons]);
        throw new RuntimeException(implode('; ', $reasons));
    }

    private function unpack(string $from, string $to, bool $gzipped): void
    {
        if (! $gzipped) {
            rename($from, $to);

            return;
        }
        $in = @gzopen($from, 'rb');
        $out = @fopen($to, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException('could not open the downloaded file');
        }
        while (! gzeof($in)) {
            $chunk = gzread($in, 1 << 20);
            if ($chunk === false) {
                throw new RuntimeException('the downloaded file is damaged');
            }
            fwrite($out, $chunk);
        }
        gzclose($in);
        fclose($out);
        if (filesize($to) === 0) {
            throw new RuntimeException('the downloaded file is empty');
        }
    }
}
