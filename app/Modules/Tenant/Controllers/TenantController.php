<?php

namespace App\Modules\Tenant\Controllers;

use App\Modules\Tenant\Application\CreateTenant\CreateTenantAction;
use App\Modules\Tenant\Application\CreateTenant\CreateTenantInput;
use App\Modules\Tenant\Requests\CreateTenantRequest;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class TenantController extends Controller
{
    public function store(CreateTenantRequest $request, CreateTenantAction $action): JsonResponse
    {
        $input = new CreateTenantInput(
            name: $request->validated('name'),
            slug: $request->validated('slug'),
            businessType: $request->validated('business_type'),
            country: $request->validated('country', 'PE'),
            currency: $request->validated('currency', 'PEN'),
            timezone: $request->validated('timezone', 'America/Lima')
        );

        $result = $action->execute($input);

        return ApiResponse::created(
            data: $result->toArray(),
            message: 'Empresa registrada correctamente en AIVA'
        );
    }
}
