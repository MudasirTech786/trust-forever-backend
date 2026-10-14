<?php

namespace App\Http\Controllers;

use App\Http\Resources\ManagedUserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    private const PROTECTED_ADMIN_EMAIL = 'trustforevermarriagebureau@gmail.com';

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $users = User::query()
            ->with('roles')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        return response()->json(['users' => ManagedUserResource::collection($users)->resolve()]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['user' => (new ManagedUserResource($user->load('roles')))->resolve()]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->filled('role_id')) {
            abort_unless($request->user()->can('users.roles.assign'), 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('guard_name', $this->guardName())],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        if (! empty($validated['role_id'])) {
            $user->assignRole(Role::findOrFail($validated['role_id']));
        }

        return response()->json(['user' => (new ManagedUserResource($user->load('roles')))->resolve()], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if ($request->filled('role_id')) {
            abort_unless($request->user()->can('users.roles.assign'), 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
            'role_id' => ['sometimes', 'nullable', 'integer', Rule::exists('roles', 'id')->where('guard_name', $this->guardName())],
        ]);

        $role = isset($validated['role_id']) ? Role::findOrFail($validated['role_id']) : null;
        $isSelf = $request->user()->is($user);
        $isProtectedAdmin = strcasecmp($user->email, self::PROTECTED_ADMIN_EMAIL) === 0;

        if ($isProtectedAdmin && strcasecmp($validated['email'], self::PROTECTED_ADMIN_EMAIL) !== 0) {
            throw ValidationException::withMessages([
                'email' => ['The primary administrator email cannot be changed here.'],
            ]);
        }

        if ($role && ($isSelf || $isProtectedAdmin) && ! $user->roles()->whereKey($role->getKey())->exists()) {
            throw ValidationException::withMessages([
                'role_id' => ['This account’s role cannot be changed here because it protects administrator access.'],
            ]);
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ])->save();
        if ($role && ! $isSelf && ! $isProtectedAdmin) {
            $user->syncRoles($role);
        }

        return response()->json(['user' => (new ManagedUserResource($user->load('roles')))->resolve()]);
    }

    public function updatePassword(Request $request, User $user): Response
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();

        return response()->noContent();
    }

    public function destroy(Request $request, User $user): Response
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages(['user' => ['You cannot delete the account you are currently using.']]);
        }

        if (strcasecmp($user->email, self::PROTECTED_ADMIN_EMAIL) === 0) {
            throw ValidationException::withMessages(['user' => ['The primary administrator account cannot be deleted.']]);
        }

        if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
            throw ValidationException::withMessages(['user' => ['The last administrator account cannot be deleted.']]);
        }

        $user->delete();

        return response()->noContent();
    }

    public function roleOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['user.create', 'user.update']), 403);

        $roles = Role::query()
            ->where('guard_name', $this->guardName())
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['roles' => $roles]);
    }

    private function guardName(): string
    {
        return Guard::getDefaultName(User::class);
    }
}
