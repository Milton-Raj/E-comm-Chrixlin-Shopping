<?php

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

it('registers a customer, signs them in and sends a verification email', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Asha',
        'email' => 'ASHA@Example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'asha@example.test')
        ->assertJsonPath('data.email_verified', false)
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.password');

    $user = User::where('email', 'asha@example.test')->firstOrFail();
    expect($user->hasRole('customer'))->toBeTrue()
        ->and($user->isStaff())->toBeFalse()
        ->and($user->uuid)->not->toBeEmpty();
    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('validates registration input', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => '',
        'email' => 'taken@example.test',
        'password' => 'short',
        'password_confirmation' => 'different',
    ]);

    expect($response)->toBeApiError(422);
    $response->assertJsonPath('message', 'Validation failed')
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('does not run breach checks on customer passwords', function () {
    // Owner decision 2026-10-09: customers are never turned away with a "leaked password" message.
    $hash = strtoupper(sha1('correct-horse-battery'));
    $this->pwnedPasswordsBody = substr($hash, 5).':42';

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Asha',
        'email' => 'asha@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertCreated();
});

it('ignores attempts to mass-assign privileged fields', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Asha',
        'email' => 'asha@example.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'is_active' => false,
        'email_verified_at' => now()->toIso8601String(),
        'roles' => ['super-admin'],
    ])->assertCreated();

    $user = User::where('email', 'asha@example.test')->firstOrFail();
    expect($user->email_verified_at)->toBeNull()
        ->and($user->hasRole('super-admin'))->toBeFalse();
});
