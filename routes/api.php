    <?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:6,1')
    ->name('login');

Route::get('/public/trending-profiles', [ProfileController::class, 'publicTrending'])
    ->middleware('throttle:60,1')
    ->name('public.trending-profiles');

Route::get('/public/profiles/search', [ProfileController::class, 'publicSearch'])
    ->middleware('throttle:60,1')
    ->name('public.profiles.search');

Route::get('/public/profiles/{profileId}', [ProfileController::class, 'publicShow'])
    ->middleware('throttle:60,1')
    ->name('public.profiles.show');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [AdminAuthController::class, 'me'])->name('me');
    Route::post('/me', [AdminAuthController::class, 'updateProfile'])->name('me.update');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::get('/me/permissions', [UserRoleController::class, 'currentPermissions'])->name('me.permissions');

    Route::get('/user-management/roles', [UserManagementController::class, 'roleOptions']);
    Route::get('/users', [UserManagementController::class, 'index'])->middleware('can:user.view');
    Route::post('/users', [UserManagementController::class, 'store'])->middleware('can:user.create');
    Route::get('/users/{user}', [UserManagementController::class, 'show'])->middleware('can:user.view');
    Route::match(['put', 'patch'], '/users/{user}', [UserManagementController::class, 'update'])->middleware('can:user.update');
    Route::put('/users/{user}/password', [UserManagementController::class, 'updatePassword'])->middleware('can:user.update');
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->middleware('can:user.delete');

    Route::get('/profiles', [ProfileController::class, 'index'])->middleware('can:profiles.view');
    Route::get('/dashboard/profile-summary', [ProfileController::class, 'dashboardSummary'])->middleware('can:profiles.view');
    Route::post('/profiles', [ProfileController::class, 'store'])->middleware('can:profiles.create');
    Route::get('/profiles/{profile}', [ProfileController::class, 'show'])->middleware('can:profiles.view');
    Route::match(['put', 'patch'], '/profiles/{profile}', [ProfileController::class, 'update'])->middleware('can:profiles.edit');
    Route::delete('/profiles/{profile}', [ProfileController::class, 'destroy'])->middleware('can:profiles.delete');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('can:roles.view');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('can:roles.create');
    Route::get('/roles/{role}', [RoleController::class, 'show'])->middleware('can:roles.view');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('can:roles.edit');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('can:roles.delete');
    Route::put('/roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->middleware('can:roles.edit');

    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('can:permissions.view');
    Route::post('/permissions', [PermissionController::class, 'store'])->middleware('can:permissions.create');
    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->middleware('can:permissions.edit');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('can:permissions.delete');

    Route::get('/users/{user}/roles', [UserRoleController::class, 'roles'])->middleware('can:users.roles.view');
    Route::post('/users/{user}/roles', [UserRoleController::class, 'assignRoles'])->middleware('can:users.roles.assign');
    Route::put('/users/{user}/roles', [UserRoleController::class, 'syncRoles'])->middleware('can:users.roles.assign');
    Route::delete('/users/{user}/roles', [UserRoleController::class, 'removeRoles'])->middleware('can:users.roles.remove');
    Route::get('/users/{user}/permissions', [UserRoleController::class, 'permissions'])->middleware('can:users.roles.view');
});
