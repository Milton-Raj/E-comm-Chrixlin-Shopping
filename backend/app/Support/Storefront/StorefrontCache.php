<?php

namespace App\Support\Storefront;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the Next.js storefront to drop cached pages after catalog/content changes
 * (ARCHITECTURE §6.17). Best effort: failures are logged, pages still expire within minutes.
 */
class StorefrontCache
{
    /** @param  list<string>  $tags */
    public static function invalidate(array $tags): void
    {
        $url = config('commerce.revalidate.url');
        $secret = config('commerce.revalidate.secret');
        if (! $url || ! $secret || app()->runningUnitTests()) {
            return;
        }

        DB::afterCommit(function () use ($url, $secret, $tags) {
            try {
                Http::timeout(3)->withHeaders(['X-Revalidate-Secret' => $secret])->post($url, ['tags' => array_values(array_unique($tags))]);
            } catch (Throwable $e) {
                Log::channel('api')->notice('Storefront revalidation failed.', ['tags' => $tags, 'error' => $e->getMessage()]);
            }
        });
    }
}
