<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->customer()->create(['password' => 'correct-horse-battery']);
    DB::table('sessions')->insert([
        ['id' => 'phone-session', 'user_id' => $this->user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'Phone', 'payload' => '', 'last_activity' => time()],
        ['id' => 'laptop-session', 'user_id' => $this->user->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'Laptop', 'payload' => '', 'last_activity' => time() - 60],
    ]);
});

it('lists sessions without exposing session ids', function () {
    $this->actingAs($this->user)->getJson('/api/v1/account/sessions')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('data.0.id')
        ->assertJsonPath('data.0.user_agent', 'Phone');
});

it('logs out other devices after confirming the password', function () {
    $before = $this->user->remember_token;

    $this->actingAs($this->user)
        ->postJson('/api/v1/account/sessions/logout-others', ['password' => 'wrong'])
        ->assertJsonValidationErrors('password');
    expect(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(2);

    $this->postJson('/api/v1/account/sessions/logout-others', ['password' => 'correct-horse-battery'])
        ->assertOk()
        ->assertJsonPath('data.revoked', 2);

    expect(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(0)
        ->and($this->user->fresh()->remember_token)->not->toBe($before);
});

it('changes the password and signs out other devices', function () {
    $this->actingAs($this->user)->putJson('/api/v1/account/password', [
        'current_password' => 'correct-horse-battery',
        'password' => 'another-good-password',
        'password_confirmation' => 'another-good-password',
    ])->assertOk();

    expect(Hash::check('another-good-password', $this->user->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(0);
});

it('requires the current password to change it', function () {
    $this->actingAs($this->user)->putJson('/api/v1/account/password', [
        'current_password' => 'nope',
        'password' => 'another-good-password',
        'password_confirmation' => 'another-good-password',
    ])->assertJsonValidationErrors('current_password');
});
