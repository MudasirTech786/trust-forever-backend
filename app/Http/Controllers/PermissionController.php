<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::query()
            ->where('guard_name', $this->guardName())
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $permission): array => $this->permissionData($permission));

        return response()->json(['permissions' => $permissions]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->where('guard_name', $this->guardName())],
        ]);

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => $this->guardName(),
        ]);

        return response()->json(['permission' => $this->permissionData($permission)], 201);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $this->ensureGuard($permission->guard_name);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->where('guard_name', $this->guardName())->ignore($permission->getKey())],
        ]);

        $permission->name = $validated['name'];
        $permission->save();

        return response()->json(['permission' => $this->permissionData($permission)]);
    }

    public function destroy(Permission $permission): Response
    {
        $this->ensureGuard($permission->guard_name);
        $permission->delete();

        return response()->noContent();
    }

    /**
     * @return array{id: int, name: string, guard_name: string}
     */
    private function permissionData(Permission $permission): array
    {
        return [
            'id' => $permission->getKey(),
            'name' => $permission->name,
            'guard_name' => $permission->guard_name,
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
