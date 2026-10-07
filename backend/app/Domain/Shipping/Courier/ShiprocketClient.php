<?php

namespace App\Domain\Shipping\Courier;

use App\Exceptions\ApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for the Shiprocket external API. Logs in with the API user,
 * caches the bearer token (valid 10 days; we keep it 9) and retries once on 401.
 */
class ShiprocketClient
{
    private const TOKEN_CACHE_KEY = 'shipping.shiprocket.token';

    public function configured(): bool
    {
        return filled(config('shipping.shiprocket.email')) && filled(config('shipping.shiprocket.password'));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(string $path, array $body): array
    {
        return $this->send('post', $path, $body);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('get', $path, $query);
    }

    /** @return list<string> pickup location nicknames configured in Shiprocket */
    public function pickupLocations(): array
    {
        $data = $this->get('/settings/company/pickup');
        $rows = (array) data_get($data, 'data.shipping_address', []);

        return array_values(array_filter(array_map(fn ($row) => (string) data_get($row, 'pickup_location', ''), $rows)));
    }

    public function forgetToken(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $payload, bool $isRetry = false): array
    {
        $response = $this->request($method, $path, $payload, $this->token());

        if ($response->status() === 401 && ! $isRetry) {
            $this->forgetToken();

            return $this->send($method, $path, $payload, true);
        }

        if (! $response->successful()) {
            $message = $this->errorMessage($response);
            Log::channel('shipping')->error('Shiprocket request failed.', ['path' => $path, 'status' => $response->status(), 'message' => $message]);
            throw new ApiException("Shiprocket: {$message}", 502, 'courier_error');
        }

        return (array) $response->json();
    }

    /** @param array<string, mixed> $payload */
    private function request(string $method, string $path, array $payload, string $token): Response
    {
        try {
            return Http::baseUrl((string) config('shipping.shiprocket.base_url'))
                ->withToken($token)->acceptJson()->asJson()->timeout(20)
                ->{$method}($path, $payload);
        } catch (ConnectionException) {
            throw new ApiException('Shiprocket could not be reached. Try again in a minute.', 502, 'courier_unreachable');
        }
    }

    private function token(): string
    {
        if (! $this->configured()) {
            throw new ApiException('Shiprocket is not configured. Add SHIPROCKET_EMAIL and SHIPROCKET_PASSWORD to the server environment.', 409, 'courier_not_configured');
        }

        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addDays(9), function (): string {
            try {
                $response = Http::baseUrl((string) config('shipping.shiprocket.base_url'))->acceptJson()->timeout(20)
                    ->post('/auth/login', ['email' => config('shipping.shiprocket.email'), 'password' => config('shipping.shiprocket.password')]);
            } catch (ConnectionException) {
                throw new ApiException('Shiprocket could not be reached. Try again in a minute.', 502, 'courier_unreachable');
            }

            $token = (string) $response->json('token', '');
            if (! $response->successful() || $token === '') {
                Log::channel('shipping')->error('Shiprocket login failed.', ['status' => $response->status()]);
                throw new ApiException('Shiprocket rejected the API user login. Check SHIPROCKET_EMAIL and SHIPROCKET_PASSWORD (use the API user, not your main login).', 502, 'courier_auth_failed');
            }

            return $token;
        });
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('message');
        $errors = $response->json('errors');
        if (is_array($errors) && $errors !== []) {
            $first = collect($errors)->flatten()->first();
            if (is_string($first)) {
                return $first;
            }
        }

        return is_string($message) && $message !== '' ? $message : "HTTP {$response->status()}";
    }
}
