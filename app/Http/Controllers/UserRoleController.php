<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Guard;

class UserRoleController extends Controller
{
    public function currentPermissions(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->unique()->values(),
        ]);
    }

    public function roles(User $user): JsonResponse
    {
        return response()->json([
            'roles' => $user->roles()
                ->where('guard_name', $this->guardName())
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }

    public function assignRoles(Request $request, User $user): JsonResponse
    {
        $validated = $this->validatedRoles($request);
        $user->assignRole(...$validated['roles']);

        return $this->roles($user);
    }

    public function syncRoles(Request $request, User $user): JsonResponse
    {
        $validated = $this->validatedRoles($request);
        $user->syncRoles($validated['roles']);

        return $this->roles($user);
    }

    public function removeRoles(Request $request, User $user): JsonResponse
    {
        $validated = $this->validatedRoles($request);

        foreach ($validated['roles'] as $role) {
            $user->removeRole($role);
        }

        return $this->roles($user);
    }

    public function permissions(User $user): JsonResponse
    {
        return response()->json([
            'roles' => $user->roles()
                ->where('guard_name', $this->guardName())
                ->orderBy('name')
                ->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name')->unique()->values(),
        ]);
    }

    /**
     * @return array{roles: array<int, string>}
     */
    private function validatedRoles(Request $request): array
    {
        return $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => [
                'string',
                'distinct',
                Rule::exists('roles', 'name')->where('guard_name', $this->guardName()),
            ],
        ]);
    }

    private function guardName(): string
    {
        return Guard::getDefaultName(User::class);
    }
}
