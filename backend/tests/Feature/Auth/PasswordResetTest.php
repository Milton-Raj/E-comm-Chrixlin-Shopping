<?php

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('sends a reset link that opens the storefront', function () {
    Notification::fake();
    $user = User::factory()->customer()->create();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $n) use ($user) {
        $url = $n->toMail($user)->actionUrl;

        return str_starts_with($url, 'http://localhost:3000/reset-password?token=');
    });
});

it('answers identically for unknown emails', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'])
        ->assertOk()
        ->assertJsonPath('message', 'If an account exists for that email, a reset link has been sent.');

    Notification::assertNothingSent();
});

it('resets the password once and revokes all sessions', function () {
    $user = User::factory()->customer()->create();
    $token = Password::broker()->createToken($user);
    DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $payload = ['token' => $token, 'email' => $user->email, 'password' => 'brand-new-password-1', 'password_confirmation' => 'brand-new-password-1'];

    $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();
    expect(Hash::check('brand-new-password-1', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);

    $this->postJson('/api/v1/auth/reset-password', $payload)->assertStatus(422);
});
