<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(fn () => Notification::fake());

it('lets customers sign up with any password of 6 or more characters', function () {
    $this->postJson('/api/v1/auth/register', ['name' => 'Meera', 'email' => 'meera@example.test', 'password' => 'abc123', 'password_confirmation' => 'abc123'])
        ->assertCreated();

    $this->flushSession();
    $this->postJson('/api/v1/auth/register', ['name' => 'Ravi', 'email' => 'ravi@example.test', 'password' => 'abc', 'password_confirmation' => 'abc'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
});

it('keeps the stronger rule for staff password resets', function () {
    $staff = User::factory()->staff('order-manager')->create();
    $customer = User::factory()->customer()->create();

    $this->postJson('/api/v1/auth/reset-password', ['token' => Password::createToken($staff), 'email' => $staff->email, 'password' => 'abc123', 'password_confirmation' => 'abc123'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->postJson('/api/v1/auth/reset-password', ['token' => Password::createToken($customer), 'email' => $customer->email, 'password' => 'abc123', 'password_confirmation' => 'abc123'])
        ->assertOk();
});
