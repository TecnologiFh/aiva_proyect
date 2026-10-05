<?php

namespace App\Modules\Identity\Controllers;

use App\Core\Services\TenantContext;
use App\Modules\Identity\Application\AuthenticateUser\AuthenticateUserAction;
use App\Modules\Identity\Application\AuthenticateUser\AuthenticateUserInput;
use App\Modules\Identity\Application\RegisterUser\RegisterUserAction;
use App\Modules\Identity\Application\RegisterUser\RegisterUserInput;
use App\Modules\Identity\Requests\LoginRequest;
use App\Modules\Identity\Requests\RegisterUserRequest;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuthController extends Controller
{
    public function register(RegisterUserRequest $request, RegisterUserAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();
        if (!$tenantId) {
            return ApiResponse::error(message: 'Empresa no especificada o no válida para el registro', code: 422);
        }

        $input = new RegisterUserInput(
            tenantId: $tenantId,
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            role: $request->validated('role', 'OWNER')
        );

        $result = $action->execute($input);

        return ApiResponse::created(
            data: $result->toArray(),
            message: 'Usuario registrado correctamente en AIVA'
        );
    }

    public function login(LoginRequest $request, AuthenticateUserAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();
        if (!$tenantId) {
            return ApiResponse::error(message: 'Empresa no identificada en la petición (se requiere subdominio o X-Tenant-Slug)', code: 422);
        }

        $input = new AuthenticateUserInput(
            tenantId: $tenantId,
            email: $request->validated('email'),
            password: $request->validated('password')
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Inicio de sesión exitoso'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(message: 'Sesión cerrada correctamente');
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = TenantContext::getTenant();

        return ApiResponse::success(data: [
            'user' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status->value,
            ],
            'tenant' => $tenant ? [
                'uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'currency' => $tenant->currency,
            ] : null,
        ]);
    }
}
