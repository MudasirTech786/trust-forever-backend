<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->where('guard_name', $this->guardName())
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => $this->roleData($role));

        return response()->json(['roles' => $roles]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', $this->guardName())],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => $this->guardName(),
        ]);

        return response()->json(['role' => $this->roleData($role)], 201);
    }

    public function show(Role $role): JsonResponse
    {
        $this->ensureGuard($role->guard_name);

        return response()->json(['role' => $this->roleData($role->load('permissions'))]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->ensureGuard($role->guard_name);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', $this->guardName())->ignore($role->getKey())],
        ]);

        $role->name = $validated['name'];
        $role->save();

        return response()->json(['role' => $this->roleData($role->load('permissions'))]);
    }

    public function destroy(Role $role): Response
    {
        $this->ensureGuard($role->guard_name);

        if (strcasecmp($role->name, 'admin') === 0) {
            throw ValidationException::withMessages([
                'role' => ['The Administrator role cannot be deleted.'],
            ]);
        }

        $role->delete();

        return response()->noContent();
    }

    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        $this->ensureGuard($role->guard_name);

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => [
                'integer',
                'distinct',
                Rule::exists('permissions', 'id')->where('guard_name', $this->guardName()),
            ],
        ]);

        $permissions = Permission::query()->whereIn('id', $validated['permissions'])->get();
        $role->syncPermissions($permissions);

        return response()->json(['role' => $this->roleData($role->load('permissions'))]);
    }

    /**
     * @return array{id: int, name: string, guard_name: string, permissions: array<int, array{id: int, name: string}>}
     */
    private function roleData(Role $role): array
    {
        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions
                ->map(fn (Permission $permission): array => [
                    'id' => $permission->getKey(),
                    'name' => $permission->name,
                ])
                ->values()
                ->all(),
        ];
    }

    private function guardName(): string
    {
        return Guard::getDefaultName(User::class);
    }

    private function ensureGuard(string $guardName): void
    {
        abort_unless($guardName === $this->guardName(), 404);
    }
}
