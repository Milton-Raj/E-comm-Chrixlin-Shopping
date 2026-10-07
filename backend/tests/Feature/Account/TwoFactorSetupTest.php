<?php

use App\Models\AuditLog;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->user = User::factory()->customer()->create(['password' => 'correct-horse-battery']);
});

it('enables 2FA in two steps and returns recovery codes once', function () {
    $setup = $this->actingAs($this->user)
        ->postJson('/api/v1/account/two-factor', ['password' => 'correct-horse-battery'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['secret', 'otpauth_url', 'qr_svg']])
        ->json('data');

    expect($this->user->fresh()->hasTwoFactorEnabled())->toBeFalse();

    $codes = $this->postJson('/api/v1/account/two-factor/confirm', [
        'code' => app(Google2FA::class)->getCurrentOtp($setup['secret']),
    ])->assertOk()->json('data.recovery_codes');

    expect($codes)->toHaveCount(8)
        ->and($this->user->fresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and(AuditLog::where('action', 'user.two_factor_enabled')->exists())->toBeTrue();
});

it('requires the current password to start setup', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/account/two-factor', ['password' => 'wrong'])
        ->assertJsonValidationErrors('password');
});

it('rejects a wrong confirmation code', function () {
    $this->actingAs($this->user)->postJson('/api/v1/account/two-factor', ['password' => 'correct-horse-battery']);

    $this->postJson('/api/v1/account/two-factor/confirm', ['code' => '000000'])
        ->assertJsonValidationErrors('code');
    expect($this->user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('lets customers disable 2FA', function () {
    $user = User::factory()->customer()->withTwoFactor()->create(['password' => 'correct-horse-battery']);

    $this->actingAs($user)
        ->deleteJson('/api/v1/account/two-factor', ['password' => 'correct-horse-battery'])
        ->assertOk();

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('does not let staff disable 2FA', function () {
    $staff = User::factory()->staff('order-manager')->create(['password' => 'correct-horse-battery']);

    $response = $this->actingAs($staff)->deleteJson('/api/v1/account/two-factor', ['password' => 'correct-horse-battery']);

    expect($response)->toBeApiError(403, 'two_factor_required_for_staff');
    expect($staff->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

it('never exposes 2FA secrets in the profile', function () {
    $user = User::factory()->customer()->withTwoFactor()->create();

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.two_factor_enabled', true)
        ->assertJsonMissingPath('data.two_factor_secret')
        ->assertJsonMissingPath('data.two_factor_recovery_codes');
});
