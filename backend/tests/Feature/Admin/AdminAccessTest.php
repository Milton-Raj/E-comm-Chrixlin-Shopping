<?php

use App\Http\Middleware\EnsureAdminAccess;
use App\Models\User;

it('returns the staff member with effective permissions', function () {
    $staff = User::factory()->staff('content-manager')->create();

    $this->actingAs($staff)->getJson('/api/v1/admin/me')
        ->assertOk()
        ->assertJsonPath('data.roles', ['content-manager'])
        ->assertJsonPath('data.permissions', ['content.manage', 'media.manage']);
});

it('gives super-admins every permission', function () {
    $admin = User::factory()->staff('super-admin')->create();

    $permissions = $this->actingAs($admin)->getJson('/api/v1/admin/me')->assertOk()->json('data.permissions');

    expect($permissions)->toContain('roles.manage', 'orders.refund', 'settings.manage')
        ->and($admin->can('orders.refund'))->toBeTrue();
});

it('blocks guests, customers and staff without 2FA', function () {
    expect($this->getJson('/api/v1/admin/me'))->toBeApiError(401);

    $customer = User::factory()->customer()->create();
    expect($this->actingAs($customer)->getJson('/api/v1/admin/me'))->toBeApiError(403);

    $staff = User::factory()->staff('order-manager')->create(['two_factor_secret' => null, 'two_factor_confirmed_at' => null]);
    expect($this->actingAs($staff)->getJson('/api/v1/admin/me'))->toBeApiError(403, 'two_factor_required');
});

it('expires idle admin sessions', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->withSession([EnsureAdminAccess::LAST_ACTIVITY_KEY => now()->subMinutes(31)->timestamp]);

    expect($this->getJson('/api/v1/admin/me'))->toBeApiError(401, 'session_expired');
});

it('denies permissions the role does not hold', function () {
    $staff = User::factory()->staff('content-manager')->create();

    expect($staff->can('orders.refund'))->toBeFalse()
        ->and($staff->can('content.manage'))->toBeTrue();
});
