<?php

use App\Domain\Identity\PermissionCatalog;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    $this->owner = User::factory()->staff('super-admin')->create(['password' => 'owner-password-1']);
});

it('groups every permission exactly once for the checkboxes', function () {
    $grouped = collect(PermissionCatalog::groups())->pluck('permissions')->flatten();

    expect($grouped->duplicates()->all())->toBe([])
        ->and($grouped->sort()->values()->all())->toBe(collect(array_keys(PermissionCatalog::permissions()))->sort()->values()->all());
});

it('creates a custom role, assigns it to several staff and edits it', function () {
    $this->actingAs($this->owner)->postJson('/api/v1/admin/roles', [
        'name' => 'Packing Staff', 'permissions' => ['orders.view', 'shipments.manage'], 'password' => 'owner-password-1',
    ])->assertCreated()->assertJsonPath('data.name', 'packing-staff');

    foreach (['a@example.test', 'b@example.test'] as $email) {
        $this->actingAs($this->owner)->postJson('/api/v1/admin/staff', ['name' => 'Packer', 'email' => $email, 'role' => 'packing-staff', 'password' => 'owner-password-1'])->assertCreated();
    }
    $packer = User::where('email', 'a@example.test')->first();
    expect($packer->can('shipments.manage'))->toBeTrue()->and($packer->can('orders.refund'))->toBeFalse();

    $roles = $this->actingAs($this->owner)->getJson('/api/v1/admin/roles')->assertOk()->json('data');
    $row = collect($roles['roles'])->firstWhere('name', 'packing-staff');
    expect($row['staff_count'])->toBe(2)->and($roles['groups'])->not->toBeEmpty();

    $this->actingAs($this->owner)->putJson('/api/v1/admin/roles/packing-staff', [
        'name' => 'Dispatch team', 'permissions' => ['orders.view', 'orders.edit', 'shipments.manage'], 'password' => 'owner-password-1',
    ])->assertOk()->assertJsonPath('data.name', 'dispatch-team');

    expect($packer->fresh()->hasRole('dispatch-team'))->toBeTrue()
        ->and($packer->fresh()->can('orders.edit'))->toBeTrue()
        ->and(AuditLog::where('action', 'role.updated')->exists())->toBeTrue();
});

it('lets the owner edit default roles and re-seeding keeps the edit', function () {
    $this->actingAs($this->owner)->putJson('/api/v1/admin/roles/content-manager', ['permissions' => ['content.manage'], 'password' => 'owner-password-1'])->assertOk();

    (new PermissionSeeder)->run();

    expect(Role::findByName('content-manager', 'web')->permissions->pluck('name')->all())->toBe(['content.manage']);
});

it('refuses to delete a role in use, then deletes it once free', function () {
    $staff = User::factory()->staff('content-manager')->create();

    expect($this->actingAs($this->owner)->deleteJson('/api/v1/admin/roles/content-manager', ['password' => 'owner-password-1']))->toBeApiError(409, 'role_in_use');

    $staff->syncRoles(['order-manager']);
    $this->actingAs($this->owner)->deleteJson('/api/v1/admin/roles/content-manager', ['password' => 'owner-password-1'])->assertOk();
    expect(Role::where('name', 'content-manager')->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'role.deleted')->exists())->toBeTrue();
});

it('protects the owner role, names and the password check', function () {
    expect($this->actingAs($this->owner)->putJson('/api/v1/admin/roles/super-admin', ['permissions' => ['orders.view'], 'password' => 'owner-password-1']))->toBeApiError(403, 'role_locked');
    expect($this->actingAs($this->owner)->postJson('/api/v1/admin/roles', ['name' => 'Order manager', 'permissions' => ['orders.view'], 'password' => 'owner-password-1']))->toBeApiError(422, 'role_name_taken');
    $this->actingAs($this->owner)->postJson('/api/v1/admin/roles', ['name' => 'Helpers', 'permissions' => ['orders.view'], 'password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->actingAs($this->owner)->postJson('/api/v1/admin/roles', ['name' => 'Helpers', 'permissions' => [], 'password' => 'owner-password-1'])->assertUnprocessable()->assertJsonValidationErrors('permissions');
    $this->actingAs($this->owner)->postJson('/api/v1/admin/roles', ['name' => 'Helpers', 'permissions' => ['orders.delete-everything'], 'password' => 'owner-password-1'])->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
});

it('requires roles.manage, and administrators cannot hand out access they lack', function () {
    $admin = User::factory()->staff('administrator')->create(['password' => 'admin-password-1']);

    Role::create(['name' => 'role-keepers', 'guard_name' => 'web'])->syncPermissions(['roles.manage']);

    expect($this->actingAs($admin)->getJson('/api/v1/admin/roles'))->toBeApiError(403);
    expect($this->actingAs($admin)->postJson('/api/v1/admin/staff', ['name' => 'X', 'email' => 'x@example.test', 'role' => 'role-keepers', 'password' => 'admin-password-1']))
        ->toBeApiError(403, 'role_exceeds_your_access');
});
