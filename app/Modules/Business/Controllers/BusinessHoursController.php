<?php

namespace App\Modules\Business\Controllers;

use App\Core\Services\TenantContext;
use App\Modules\Business\Application\BusinessHours\SetBusinessHours\SetBusinessHoursAction;
use App\Modules\Business\Application\BusinessHours\SetBusinessHours\SetBusinessHoursInput;
use App\Modules\Business\Repositories\BusinessHourRepository;
use App\Modules\Business\Requests\SetBusinessHoursRequest;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class BusinessHoursController extends Controller
{
    public function __construct(
        private readonly BusinessHourRepository $hourRepository
    ) {}

    public function show(): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();
        $hours = $this->hourRepository->getByTenantId($tenantId)->map(fn($h) => [
            'day_of_week' => $h->day_of_week,
            'shift_number' => $h->shift_number,
            'open_time' => $h->open_time,
            'close_time' => $h->close_time,
            'is_closed' => (bool) $h->is_closed,
        ]);

        return ApiResponse::success(
            data: $hours,
            message: 'Horarios de atención del negocio recuperados exitosamente'
        );
    }

    public function update(SetBusinessHoursRequest $request, SetBusinessHoursAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new SetBusinessHoursInput(
            tenantId: $tenantId,
            hours: $request->validated('hours', [])
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Horarios de atención del negocio actualizados correctamente'
        );
    }
}
