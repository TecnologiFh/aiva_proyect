<?php

namespace App\Modules\Identity\Controllers;

use App\Modules\Identity\Repositories\RoleRepository;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleRepository $roleRepository
    ) {}

    public function index(): JsonResponse
    {
        $roles = $this->roleRepository->all()->map(fn($role) => [
            'uuid' => $role->uuid,
            'name' => $role->name,
            'description' => $role->description,
            'permissions' => $role->permissions->pluck('code')->toArray(),
        ]);

        return ApiResponse::success(
            data: $roles,
            message: 'Catálogo de roles y permisos recuperado exitosamente'
        );
    }
}
