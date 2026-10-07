<?php

namespace Tests;

use App\Domain\Cart\CartService;
use Database\Factories\UserFactory;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use PragmaRX\Google2FA\Google2FA;

abstract class TestCase extends BaseTestCase
{
    /** Roles and permissions exist in every environment, so every DB test starts with them. */
    protected bool $seed = true;

    protected string $seeder = PermissionSeeder::class;

    public const FRONTEND_ORIGIN = 'http://localhost:3000';

    /** Body returned by the fake HIBP range API; empty = password not breached. */
    protected string $pwnedPasswordsBody = '';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        // Password::uncompromised() calls the HIBP range API; "not found" keeps test passwords valid.
        Http::fake(['api.pwnedpasswords.com/*' => fn () => Http::response($this->pwnedPasswordsBody, 200)]);

        // Requests look like they come from the storefront, so Sanctum treats them as stateful SPA calls.
        $this->withHeaders(['Origin' => self::FRONTEND_ORIGIN, 'Referer' => self::FRONTEND_ORIGIN.'/', 'Accept' => 'application/json']);
        // Like the storefront's fetch(..., { credentials: 'include' }): JSON requests carry cookies.
        $this->withCredentials();
    }

    /**
     * Behave like a browser for the guest cart: keep the httpOnly cart cookie between requests
     * (it is excluded from cookie encryption, so it is sent unencrypted).
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === CartService::COOKIE) {
                $this->unencryptedCookies[$cookie->getName()] = $cookie->getValue();
            }
        }

        return $response;
    }

    /** A currently valid TOTP code for factory users created with ->withTwoFactor(). */
    protected function totp(string $secret = UserFactory::TWO_FACTOR_TEST_SECRET): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }
}
