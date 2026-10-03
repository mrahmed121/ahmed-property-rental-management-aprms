<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BuildingController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\UnitController;
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

        // Dashboard (real query-backed stats)
        Route::get('dashboard/stats', [DashboardController::class, 'stats'])->middleware('permission:dashboard.view');

        // P2 — Property domain
        Route::get('properties', [PropertyController::class, 'index'])->middleware('permission:properties.view');
        Route::post('properties', [PropertyController::class, 'store'])->middleware('permission:properties.manage');
        Route::get('properties/{property}', [PropertyController::class, 'show'])->middleware('permission:properties.view');
        Route::put('properties/{property}', [PropertyController::class, 'update'])->middleware('permission:properties.manage');
        Route::delete('properties/{property}', [PropertyController::class, 'destroy'])->middleware('permission:properties.manage');
        Route::post('properties/{property}/restore', [PropertyController::class, 'restore'])->middleware('permission:properties.manage');

        Route::get('buildings', [BuildingController::class, 'index'])->middleware('permission:buildings.view');
        Route::post('buildings', [BuildingController::class, 'store'])->middleware('permission:buildings.manage');
        Route::get('buildings/{building}', [BuildingController::class, 'show'])->middleware('permission:buildings.view');
        Route::put('buildings/{building}', [BuildingController::class, 'update'])->middleware('permission:buildings.manage');
        Route::delete('buildings/{building}', [BuildingController::class, 'destroy'])->middleware('permission:buildings.manage');
        Route::post('buildings/{building}/restore', [BuildingController::class, 'restore'])->middleware('permission:buildings.manage');

        Route::get('units', [UnitController::class, 'index'])->middleware('permission:units.view');
        Route::post('units', [UnitController::class, 'store'])->middleware('permission:units.manage');
        Route::get('units/{unit}', [UnitController::class, 'show'])->middleware('permission:units.view');
        Route::put('units/{unit}', [UnitController::class, 'update'])->middleware('permission:units.manage');
        Route::delete('units/{unit}', [UnitController::class, 'destroy'])->middleware('permission:units.manage');
        Route::post('units/{unit}/restore', [UnitController::class, 'restore'])->middleware('permission:units.manage');

        Route::get('documents', [DocumentController::class, 'index'])->middleware('permission:documents.view');
        Route::post('documents', [DocumentController::class, 'store'])->middleware('permission:documents.manage');
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])->middleware('permission:documents.view');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->middleware('permission:documents.manage');
    });
});
