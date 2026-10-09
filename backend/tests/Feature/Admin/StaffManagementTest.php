<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\Auth\StaffInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->owner = User::factory()->staff('super-admin')->create(['password' => 'owner-password-1']);
});

it('lists staff and the roles that can be given', function () {
    User::factory()->staff('order-manager')->create();
    User::factory()->customer()->create();

    $this->actingAs($this->owner)->getJson('/api/v1/admin/staff')
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.uuid', fn ($v) => is_string($v));

    $roles = $this->actingAs($this->owner)->getJson('/api/v1/admin/staff/roles')->assertOk()->json('data');
    expect(collect($roles)->pluck('name'))->toContain('order-manager', 'super-admin')->not->toContain('customer');
});

it('invites a new staff member with a set-your-password email', function () {
    $this->actingAs($this->owner)->postJson('/api/v1/admin/staff', [
        'name' => 'Priya', 'email' => 'Priya@Example.test', 'role' => 'order-manager', 'password' => 'owner-password-1',
    ])->assertCreated()->assertJsonPath('data.role', 'order-manager')->assertJsonPath('data.email', 'priya@example.test');

    $user = User::where('email', 'priya@example.test')->firstOrFail();
    expect($user->hasRole('order-manager'))->toBeTrue()
        ->and(DB::table('password_reset_tokens')->where('email', 'priya@example.test')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'staff.created')->exists())->toBeTrue();
    Notification::assertSentTo($user, StaffInvitationNotification::class, fn ($n) => $n->token !== null && $n->role === 'order-manager');
});

it('promotes an existing customer without touching their password', function () {
    $customer = User::factory()->customer()->create(['email' => 'shopper@example.test']);
    $hash = $customer->password;

    $this->actingAs($this->owner)->postJson('/api/v1/admin/staff', [
        'name' => 'Ignored', 'email' => 'shopper@example.test', 'role' => 'content-manager', 'password' => 'owner-password-1',
    ])->assertCreated();

    $customer->refresh();
    expect($customer->hasRole('content-manager'))->toBeTrue()->and($customer->hasRole('customer'))->toBeTrue()
        ->and($customer->password)->toBe($hash);
    Notification::assertSentTo($customer, StaffInvitationNotification::class, fn ($n) => $n->token === null);
});

it('requires the current password and refuses duplicates', function () {
    $staff = User::factory()->staff('order-manager')->create();

    $this->actingAs($this->owner)->postJson('/api/v1/admin/staff', ['name' => 'X', 'email' => 'x@example.test', 'role' => 'order-manager', 'password' => 'wrong'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    expect($this->actingAs($this->owner)->postJson('/api/v1/admin/staff', ['name' => 'X', 'email' => $staff->email, 'role' => 'order-manager', 'password' => 'owner-password-1']))
        ->toBeApiError(409, 'already_staff');
    $this->actingAs($this->owner)->postJson('/api/v1/admin/staff', ['name' => 'X', 'email' => 'y@example.test', 'role' => 'customer', 'password' => 'owner-password-1'])
        ->assertUnprocessable()->assertJsonValidationErrors('role');
});

it('changes a role and deactivates with sign-out everywhere', function () {
    $staff = User::factory()->staff('order-manager')->create();
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($this->owner)->patchJson("/api/v1/admin/staff/{$staff->uuid}", ['role' => 'finance-manager', 'password' => 'owner-password-1'])
        ->assertOk()->assertJsonPath('data.role', 'finance-manager');
    expect($staff->fresh()->getRoleNames()->all())->toBe(['finance-manager'])
        ->and(DB::table('sessions')->where('user_id', $staff->id)->exists())->toBeTrue();

    $this->actingAs($this->owner)->patchJson("/api/v1/admin/staff/{$staff->uuid}", ['is_active' => false, 'password' => 'owner-password-1'])
        ->assertOk()->assertJsonPath('data.is_active', false);
    expect(DB::table('sessions')->where('user_id', $staff->id)->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'staff.role_changed')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'staff.deactivated')->exists())->toBeTrue();

    $this->flushSession();
    expect($this->actingAs($staff->fresh())->getJson('/api/v1/admin/me'))->toBeApiError(403);
});

it('resets staff 2FA and resends invitations', function () {
    $staff = User::factory()->staff('order-manager')->create();

    $this->actingAs($this->owner)->postJson("/api/v1/admin/staff/{$staff->uuid}/two-factor-reset", ['password' => 'owner-password-1'])
        ->assertOk()->assertJsonPath('data.two_factor_enabled', false);
    expect($staff->fresh()->hasTwoFactorEnabled())->toBeFalse()
        ->and(AuditLog::where('action', 'staff.two_factor_reset')->exists())->toBeTrue();

    $this->actingAs($this->owner)->postJson("/api/v1/admin/staff/{$staff->uuid}/invitation")->assertOk();
    Notification::assertSentTo($staff, StaffInvitationNotification::class);
});

it('stops people changing their own staff access', function () {
    expect($this->actingAs($this->owner)->patchJson("/api/v1/admin/staff/{$this->owner->uuid}", ['is_active' => false, 'password' => 'owner-password-1']))
        ->toBeApiError(403, 'cannot_manage_self');
    expect($this->owner->fresh()->is_active)->toBeTrue();
});

it('keeps owner accounts and the owner role for owners only', function () {
    $admin = User::factory()->staff('administrator')->create(['password' => 'admin-password-1']);

    expect($this->actingAs($admin)->patchJson("/api/v1/admin/staff/{$this->owner->uuid}", ['is_active' => false, 'password' => 'admin-password-1']))
        ->toBeApiError(403, 'super_admin_only');
    expect($this->actingAs($admin)->postJson('/api/v1/admin/staff', ['name' => 'X', 'email' => 'z@example.test', 'role' => 'super-admin', 'password' => 'admin-password-1']))
        ->toBeApiError(403, 'super_admin_only');
    expect($this->owner->fresh()->is_active)->toBeTrue();
});

it('denies staff without users.manage', function () {
    $staff = User::factory()->staff('order-manager')->create();

    expect($this->actingAs($staff)->getJson('/api/v1/admin/staff'))->toBeApiError(403);
    expect($this->actingAs($staff)->postJson('/api/v1/admin/staff', []))->toBeApiError(403);
});

it('refuses to manage customers through the staff endpoints', function () {
    $customer = User::factory()->customer()->create();

    expect($this->actingAs($this->owner)->patchJson("/api/v1/admin/staff/{$customer->uuid}", ['is_active' => false, 'password' => 'owner-password-1']))
        ->toBeApiError(404, 'not_staff');
});
