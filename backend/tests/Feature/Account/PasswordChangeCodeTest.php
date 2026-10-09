<?php

use App\Domain\Identity\Actions\PasswordChangeCode;
use App\Models\User;
use App\Notifications\Auth\PasswordChangeCodeNotification;
use App\Notifications\Auth\PasswordChangedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

function requestCode($test, User $user): string
{
    $test->actingAs($user)->postJson('/api/v1/account/password/code')
        ->assertOk()->assertJsonPath('data.email', 'o****@example.test');
    $code = null;
    Notification::assertSentTo($user, PasswordChangeCodeNotification::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });

    return (string) $code;
}

it('changes the password with the code emailed to the account owner', function () {
    $user = User::factory()->create(['email' => 'owner@example.test']);
    $code = requestCode($this, $user);
    expect($code)->toMatch('/^\d{6}$/');

    $this->putJson('/api/v1/account/password/with-code', ['code' => $code, 'password' => 'new-Strong-pass-42', 'password_confirmation' => 'new-Strong-pass-42'])
        ->assertOk()->assertJsonPath('message', 'Password changed. Other devices have been signed out.');

    expect(Hash::check('new-Strong-pass-42', $user->fresh()->password))->toBeTrue();
    Notification::assertSentTo($user, PasswordChangedNotification::class);
    $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_changed']);

    // Single use: the same code cannot be reused.
    expect($this->putJson('/api/v1/account/password/with-code', ['code' => $code, 'password' => 'another-Pass-77', 'password_confirmation' => 'another-Pass-77']))
        ->toBeApiError(422, 'code_expired');
});

it('rejects wrong codes and locks after five attempts', function () {
    $user = User::factory()->create(['email' => 'owner@example.test']);
    $code = requestCode($this, $user);
    $wrong = $code === '000000' ? '111111' : '000000';
    $body = fn (string $c) => ['code' => $c, 'password' => 'new-Strong-pass-42', 'password_confirmation' => 'new-Strong-pass-42'];

    for ($i = 1; $i < PasswordChangeCode::MAX_ATTEMPTS; $i++) {
        expect($this->putJson('/api/v1/account/password/with-code', $body($wrong)))->toBeApiError(422, 'code_invalid');
    }
    expect($this->putJson('/api/v1/account/password/with-code', $body($wrong)))->toBeApiError(429, 'code_locked');
    // Even the right code no longer works once locked; a new code must be requested.
    expect($this->putJson('/api/v1/account/password/with-code', $body($code)))->toBeApiError(429, 'code_locked');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('expires codes after ten minutes and validates the new password', function () {
    $user = User::factory()->create(['email' => 'owner@example.test']);
    $code = requestCode($this, $user);

    $this->putJson('/api/v1/account/password/with-code', ['code' => $code, 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertJsonValidationErrors('password');

    $this->travel(PasswordChangeCode::TTL_MINUTES + 1)->minutes();
    expect($this->putJson('/api/v1/account/password/with-code', ['code' => $code, 'password' => 'new-Strong-pass-42', 'password_confirmation' => 'new-Strong-pass-42']))
        ->toBeApiError(422, 'code_expired');
});

it('requires sign-in', function () {
    $this->postJson('/api/v1/account/password/code')->assertUnauthorized();
    $this->putJson('/api/v1/account/password/with-code', [])->assertUnauthorized();
});
