<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Identity\Actions\InviteStaffMember;
use App\Domain\Identity\Actions\UpdateStaffMember;
use App\Domain\Identity\PermissionCatalog;
use App\Domain\Identity\StaffAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/** Staff accounts and their roles (SECURITY.md §4). Every mutation is password-confirmed and audited. */
class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $staff = User::query()->role(StaffAccess::staffRoles())->with('roles')->orderBy('name')->get();

        return ApiResponse::success($staff->map(fn (User $u) => $this->present($u, $request->user())));
    }

    /** Roles with what each one can do, for the role picker. */
    public function roles(Request $request): JsonResponse
    {
        $catalog = PermissionCatalog::permissions();
        $isOwner = $request->user()->hasRole(PermissionCatalog::SUPER_ADMIN);
        $mine = $request->user()->permissionNames();
        $roles = Role::query()->whereIn('name', StaffAccess::staffRoles())->with('permissions')->orderBy('name')->get();

        return ApiResponse::success($roles->map(fn (Role $role) => [
            'name' => $role->name,
            'assignable' => $isOwner || ($role->name !== PermissionCatalog::SUPER_ADMIN && $role->permissions->pluck('name')->diff($mine)->isEmpty()),
            'everything' => $role->name === PermissionCatalog::SUPER_ADMIN,
            'permissions' => $role->permissions->pluck('name')->sort()->values()->map(fn (string $p) => $catalog[$p] ?? $p)->all(),
        ])->values());
    }

    public function store(StoreStaffRequest $request, InviteStaffMember $invite): JsonResponse
    {
        $data = $request->validated();
        $user = $invite->handle($request->user(), $data['name'], $data['email'], $data['role']);

        return ApiResponse::success($this->present($user->load('roles'), $request->user()), "Invitation sent to {$user->email}.", status: 201);
    }

    public function update(UpdateStaffRequest $request, string $staff, UpdateStaffMember $update): JsonResponse
    {
        $data = $request->validated();
        $user = $update->handle($request->user(), $this->find($staff), $data['role'] ?? null, isset($data['is_active']) ? (bool) $data['is_active'] : null);

        return ApiResponse::success($this->present($user->load('roles'), $request->user()), 'Staff member updated.');
    }

    public function resetTwoFactor(Request $request, string $staff, UpdateStaffMember $update): JsonResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password:web']], ['password.current_password' => 'Your password is incorrect.']);
        $user = $this->find($staff);
        $update->resetTwoFactor($request->user(), $user);

        return ApiResponse::success($this->present($user->load('roles'), $request->user()), 'Two-factor sign-in was reset. They will set it up again at their next sign-in.');
    }

    public function resendInvite(Request $request, string $staff, InviteStaffMember $invite): JsonResponse
    {
        $user = $this->find($staff);
        $invite->resend($request->user(), $user);

        return ApiResponse::success(message: "A new invitation was sent to {$user->email}.");
    }

    private function find(string $uuid): User
    {
        return User::query()->where('uuid', $uuid)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function present(User $user, User $viewer): array
    {
        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'role' => StaffAccess::staffRoleOf($user),
            'is_active' => $user->is_active,
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'is_you' => $user->is($viewer),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
