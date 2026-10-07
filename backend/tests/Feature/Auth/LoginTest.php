<?php

use App\Domain\Identity\Actions\AttemptLogin;
use App\Domain\Identity\Services\TwoFactorAuthenticator;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->user = User::factory()->customer()->create(['email' => 'asha@example.test', 'password' => 'correct-horse-battery']);
});

it('logs in with valid credentials', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'Asha@example.test', 'password' => 'correct-horse-battery'])
        ->assertOk()
        ->assertJsonPath('data.two_factor_required', false)
        ->assertJsonPath('data.user.uuid', $this->user->uuid);

    $this->assertAuthenticatedAs($this->user);
    expect($this->user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects invalid credentials with a generic message', function (string $email, string $password) {
    $response = $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);

    expect($response)->toBeApiError(422);
    $response->assertJsonPath('errors.email.0', __('auth.failed'));
    $this->assertGuest();
})->with([
    'wrong password' => ['asha@example.test', 'wrong-password-123'],
    'unknown email' => ['nobody@example.test', 'correct-horse-battery'],
]);

it('rejects inactive users', function () {
    $this->user->forceFill(['is_active' => false])->save();

    $this->postJson('/api/v1/auth/login', ['email' => 'asha@example.test', 'password' => 'correct-horse-battery'])
        ->assertStatus(422);
    $this->assertGuest();
});

it('locks the account out after repeated failures within an hour', function () {
    config(['commerce.security.login_lockout_attempts' => 3]);
    RateLimiter::clear(AttemptLogin::lockoutKey('asha@example.test'));

    foreach (range(1, 3) as $i) {
        RateLimiter::hit(AttemptLogin::lockoutKey('asha@example.test'), 3600);
    }

    $response = $this->postJson('/api/v1/auth/login', ['email' => 'asha@example.test', 'password' => 'correct-horse-battery']);

    expect($response)->toBeApiError(429);
    $this->assertGuest();
});

it('requires a second factor when 2FA is enabled', function () {
    $user = User::factory()->customer()->withTwoFactor()->create(['email' => 'two@example.test', 'password' => 'correct-horse-battery']);

    $this->postJson('/api/v1/auth/login', ['email' => 'two@example.test', 'password' => 'correct-horse-battery'])
        ->assertOk()
        ->assertJsonPath('data.two_factor_required', true);
    $this->assertGuest();

    $this->postJson('/api/v1/auth/two-factor/challenge', ['code' => '000000'])
        ->assertJsonValidationErrors('code');
    $this->assertGuest();

    $this->postJson('/api/v1/auth/two-factor/challenge', ['code' => $this->totp()])
        ->assertOk()
        ->assertJsonPath('data.user.uuid', $user->uuid);
    $this->assertAuthenticatedAs($user);
});

it('does not accept the same TOTP code twice', function () {
    $user = User::factory()->customer()->withTwoFactor()->create();
    $code = $this->totp();

    expect(app(TwoFactorAuthenticator::class)->verify($user, $code))->toBeTrue()
        ->and(app(TwoFactorAuthenticator::class)->verify($user->fresh(), $code))->toBeFalse();
});

it('accepts a recovery code once', function () {
    $user = User::factory()->customer()->withTwoFactor()->create(['email' => 'two@example.test', 'password' => 'correct-horse-battery']);
    $codes = app(TwoFactorAuthenticator::class)->regenerateRecoveryCodes($user);

    $this->postJson('/api/v1/auth/login', ['email' => 'two@example.test', 'password' => 'correct-horse-battery']);
    $this->postJson('/api/v1/auth/two-factor/challenge', ['recovery_code' => $codes[0]])->assertOk();

    expect($user->fresh()->two_factor_recovery_codes)->toHaveCount(7);
});

it('rejects a two-factor challenge without a pending login', function () {
    expect($this->postJson('/api/v1/auth/two-factor/challenge', ['code' => '123456']))->toBeApiError(401, 'login_expired');
});

it('logs out', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    $this->assertGuest('web');
});
