<?php

namespace App\Modules\Identity\Controllers;

use App\Core\Services\TenantContext;
use App\Modules\Identity\Application\AssignUserRole\AssignUserRoleAction;
use App\Modules\Identity\Application\AssignUserRole\AssignUserRoleInput;
use App\Modules\Identity\Application\CreateEmployee\CreateEmployeeAction;
use App\Modules\Identity\Application\CreateEmployee\CreateEmployeeInput;
use App\Modules\Identity\Application\ListUsers\ListUsersAction;
use App\Modules\Identity\Application\ListUsers\ListUsersInput;
use App\Modules\Identity\Application\TransferOwnership\TransferOwnershipAction;
use App\Modules\Identity\Application\TransferOwnership\TransferOwnershipInput;
use App\Modules\Identity\Application\UpdateUserStatus\UpdateUserStatusAction;
use App\Modules\Identity\Application\UpdateUserStatus\UpdateUserStatusInput;
use App\Modules\Identity\Requests\AssignRoleRequest;
use App\Modules\Identity\Requests\CreateEmployeeRequest;
use App\Modules\Identity\Requests\TransferOwnershipRequest;
use App\Modules\Identity\Requests\UpdateUserStatusRequest;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function index(Request $request, ListUsersAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();
        
        $input = new ListUsersInput(
            tenantId: $tenantId,
            search: $request->query('search'),
            status: $request->query('status'),
            role: $request->query('role'),
            page: (int) $request->query('page', 1),
            limit: (int) $request->query('limit', 15)
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Listado de usuarios de la empresa recuperado exitosamente'
        );
    }

    public function store(CreateEmployeeRequest $request, CreateEmployeeAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new CreateEmployeeInput(
            tenantId: $tenantId,
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            role: $request->validated('role', 'EMPLOYEE'),
            createdById: $request->user()?->id ?? 0
        );

        $result = $action->execute($input);

        return ApiResponse::created(
            data: $result->toArray(),
            message: 'Empleado registrado exitosamente en el negocio'
        );
    }

    public function updateStatus(string $uuid, UpdateUserStatusRequest $request, UpdateUserStatusAction $action): JsonResponse
    {
        $input = new UpdateUserStatusInput(
            userUuid: $uuid,
            newStatus: $request->validated('status'),
            updatedById: $request->user()?->id ?? 0
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Estado de usuario actualizado correctamente'
        );
    }

    public function assignRole(string $uuid, AssignRoleRequest $request, AssignUserRoleAction $action): JsonResponse
    {
        $input = new AssignUserRoleInput(
            userUuid: $uuid,
            roleName: $request->validated('role'),
            assignedById: $request->user()?->id ?? 0
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Rol de usuario actualizado exitosamente'
        );
    }

    public function transferOwnership(TransferOwnershipRequest $request, TransferOwnershipAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();
        $currentOwnerId = $request->user()->id;

        $input = new TransferOwnershipInput(
            tenantId: $tenantId,
            currentOwnerId: $currentOwnerId,
            newOwnerUuid: $request->validated('new_owner_uuid'),
            previousOwnerNewRole: $request->validated('previous_owner_new_role', 'ADMIN')
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Propiedad del negocio transferida exitosamente'
        );
    }
}
