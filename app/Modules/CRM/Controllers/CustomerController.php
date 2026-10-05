<?php

namespace App\Modules\CRM\Controllers;

use App\Core\Services\TenantContext;
use App\Modules\CRM\Application\ListCustomers\ListCustomersAction;
use App\Modules\CRM\Application\ListCustomers\ListCustomersInput;
use App\Modules\CRM\Models\CustomerAddress;
use App\Modules\CRM\Repositories\CustomerRepository;
use App\Modules\CRM\Repositories\CustomerTagRepository;
use App\Modules\CRM\Requests\AddCustomerAddressRequest;
use App\Modules\CRM\Requests\ManageCustomerTagsRequest;
use App\Modules\CRM\Services\CustomerBrainCompiler;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomerController extends Controller
{
    public function index(Request $request, ListCustomersAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new ListCustomersInput(
            tenantId: $tenantId,
            stage: $request->query('stage'),
            search: $request->query('search'),
            page: (int) $request->query('page', 1),
            limit: (int) $request->query('limit', 15)
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Lista de clientes del CRM recuperada exitosamente'
        );
    }

    public function show(string $uuid, CustomerRepository $repository, CustomerBrainCompiler $compiler): JsonResponse
    {
        $customer = $repository->findByUuid($uuid);
        if (!$customer) {
            return ApiResponse::notFound('Cliente no encontrado');
        }

        $brainSnapshot = $compiler->compile($customer);

        return ApiResponse::success(
            data: [
                'uuid' => $customer->uuid,
                'name' => $customer->name,
                'email' => $customer->email,
                'customer_stage' => $customer->customer_stage->value,
                'identities' => $customer->identities,
                'profile' => $customer->profile,
                'addresses' => $customer->addresses,
                'tags' => $customer->tags->pluck('name'),
                'memories' => $customer->memories,
                'timeline' => $customer->timeline,
                'customer_brain' => $brainSnapshot->toArray(),
            ],
            message: 'Perfil 360° del cliente recuperado exitosamente'
        );
    }

    public function addAddress(string $uuid, AddCustomerAddressRequest $request, CustomerRepository $repository, CustomerBrainCompiler $compiler): JsonResponse
    {
        $customer = $repository->findByUuid($uuid);
        if (!$customer) {
            return ApiResponse::notFound('Cliente no encontrado');
        }

        $tenantId = TenantContext::getTenantId();

        if ($request->boolean('is_default', false)) {
            CustomerAddress::where('customer_id', $customer->id)->update(['is_default' => false]);
        }

        $address = CustomerAddress::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'label' => $request->validated('label'),
            'address' => $request->validated('address'),
            'reference' => $request->validated('reference'),
            'latitude' => $request->validated('latitude'),
            'longitude' => $request->validated('longitude'),
            'delivery_zone' => $request->validated('delivery_zone'),
            'is_default' => $request->boolean('is_default', false),
        ]);

        $compiler->compile($customer->refresh());

        return ApiResponse::created(
            data: $address,
            message: 'Dirección registrada correctamente en la libreta del cliente'
        );
    }

    public function updateTags(string $uuid, ManageCustomerTagsRequest $request, CustomerRepository $repository, CustomerTagRepository $tagRepo, CustomerBrainCompiler $compiler): JsonResponse
    {
        $customer = $repository->findByUuid($uuid);
        if (!$customer) {
            return ApiResponse::notFound('Cliente no encontrado');
        }

        $tenantId = TenantContext::getTenantId();
        $tagIds = [];

        foreach ($request->validated('tags', []) as $tagName) {
            $tag = $tagRepo->findOrCreateTag($tenantId, $tagName, 'USER');
            $tagIds[] = $tag->id;
        }

        $customer->tags()->sync($tagIds);
        $compiler->compile($customer->refresh());

        return ApiResponse::success(
            data: ['tags' => $customer->tags->pluck('name')],
            message: 'Etiquetas del cliente actualizadas exitosamente'
        );
    }
}
