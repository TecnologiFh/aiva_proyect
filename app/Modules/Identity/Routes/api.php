<?php

use App\Modules\Identity\Controllers\AuthController;
use App\Modules\Identity\Controllers\RoleController;
use App\Modules\Identity\Controllers\UserController;
use App\Shared\Middleware\RequirePermission;
use App\Shared\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware([ResolveTenantContext::class])->group(function () {
    // Autenticación pública por tenant
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Rutas protegidas por Sanctum Token
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/profile', [AuthController::class, 'profile']);

        // Catálogo de roles
        Route::get('/identity/roles', [RoleController::class, 'index']);

        // Gestión de usuarios y personal del negocio protegidos por permisos RBAC
        Route::get('/identity/users', [UserController::class, 'index'])
            ->middleware(RequirePermission::class . ':identity:view_users');

        Route::post('/identity/users', [UserController::class, 'store'])
            ->middleware(RequirePermission::class . ':identity:manage_users');

        Route::patch('/identity/users/{uuid}/status', [UserController::class, 'updateStatus'])
            ->middleware(RequirePermission::class . ':identity:manage_users');

        Route::post('/identity/users/{uuid}/roles', [UserController::class, 'assignRole'])
            ->middleware(RequirePermission::class . ':identity:assign_roles');

        Route::post('/identity/users/transfer-ownership', [UserController::class, 'transferOwnership'])
            ->middleware(RequirePermission::class . ':identity:assign_roles');
    });
});
