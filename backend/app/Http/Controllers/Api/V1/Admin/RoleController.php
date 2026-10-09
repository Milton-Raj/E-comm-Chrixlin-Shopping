<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Identity\Actions\ManageRole;
use App\Domain\Identity\PermissionCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoleRequest;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/** Staff roles and the areas each one can open (SECURITY.md §4). Password-confirmed and audited. */
class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $catalog = PermissionCatalog::permissions();
        $roles = Role::query()->where('guard_name', 'web')->where('name', '!=', PermissionCatalog::CUSTOMER)
            ->with('permissions')->orderBy('name')->get();
        $counts = User::query()->join('user_roles', fn ($j) => $j->on('user_roles.model_id', '=', 'users.id')->where('user_roles.model_type', (new User)->getMorphClass()))
            ->whereIn('user_roles.role_id', $roles->pluck('id'))->groupBy('user_roles.role_id')
            ->selectRaw('user_roles.role_id as role_id, count(*) as aggregate')->pluck('aggregate', 'role_id');

        return ApiResponse::success([
            'roles' => $roles->map(fn (Role $role) => [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
                'staff_count' => (int) ($counts[$role->id] ?? 0),
                'is_default' => array_key_exists($role->name, PermissionCatalog::roles()),
                'locked' => in_array($role->name, ManageRole::LOCKED, true),
                'yours' => $request->user()->hasRole($role->name),
            ])->values(),
            'groups' => collect(PermissionCatalog::groups())->map(fn (array $g) => [
                'label' => $g['label'],
                'permissions' => array_map(fn (string $p) => ['name' => $p, 'description' => $catalog[$p]], $g['permissions']),
            ]),
        ]);
    }

    public function store(SaveRoleRequest $request, ManageRole $roles): JsonResponse
    {
        $role = $roles->create($request->user(), $request->validated('name'), $request->validated('permissions'));

        return ApiResponse::success(['name' => $role->name], 'Role created.', status: 201);
    }

    public function update(SaveRoleRequest $request, string $role, ManageRole $roles): JsonResponse
    {
        $updated = $roles->update($request->user(), $this->find($role), $request->validated('name'), $request->validated('permissions'));

        return ApiResponse::success(['name' => $updated->name], 'Role updated. Staff with this role get the new access straight away.');
    }

    public function destroy(Request $request, string $role, ManageRole $roles): JsonResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password:web']], ['password.current_password' => 'Your password is incorrect.']);
        $roles->delete($request->user(), $this->find($role));

        return ApiResponse::success(message: 'Role deleted.');
    }

    private function find(string $name): Role
    {
        return Role::query()->where('guard_name', 'web')->where('name', $name)->firstOrFail();
    }
}
