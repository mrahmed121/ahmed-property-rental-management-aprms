<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| APRMS API v1
|--------------------------------------------------------------------------
| All application APIs are versioned under /api/v1.
| Consistent JSON responses; validation errors via FormRequest (422);
| auth errors 401; authorization errors 403.
*/

Route::prefix('v1')->group(function () {
    // Public
    Route::get('health', HealthController::class);
    Route::post('auth/login', [AuthController::class, 'login']);

    // Authenticated (JWT)
    Route::middleware('auth:api')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);

        // Users & roles
        Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view');
        Route::post('users', [UserController::class, 'store'])->middleware('permission:users.manage');
        Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage');

        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage');
        Route::get('permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.view');

        // Settings (agency-scoped)
        Route::get('settings', [SettingController::class, 'index'])->middleware('permission:settings.view');
        Route::put('settings', [SettingController::class, 'update'])->middleware('permission:settings.manage');

        // Audit trail (read-only by design — no write routes)
        Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');
    });
});
