<?php

namespace App\Modules\Payments\Controllers;

use App\Core\Services\TenantContext;
use App\Modules\Payments\Application\CreatePaymentIntent\CreatePaymentIntentAction;
use App\Modules\Payments\Application\CreatePaymentIntent\CreatePaymentIntentInput;
use App\Modules\Payments\Application\ListPayments\ListPaymentsAction;
use App\Modules\Payments\Application\ListPayments\ListPaymentsInput;
use App\Modules\Payments\Application\ProcessPaymentWebhook\ProcessPaymentWebhookAction;
use App\Modules\Payments\Application\ProcessPaymentWebhook\ProcessPaymentWebhookInput;
use App\Modules\Payments\Requests\CreatePaymentIntentRequest;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PaymentController extends Controller
{
    public function index(Request $request, ListPaymentsAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new ListPaymentsInput(
            tenantId: $tenantId,
            status: $request->query('status'),
            page: (int) $request->query('page', 1),
            limit: (int) $request->query('limit', 15)
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Historial de transacciones de pago recuperado exitosamente'
        );
    }

    public function store(CreatePaymentIntentRequest $request, CreatePaymentIntentAction $action): JsonResponse
    {
        $input = new CreatePaymentIntentInput(
            orderUuid: $request->validated('order_uuid'),
            gateway: $request->validated('gateway'),
            paymentMethod: $request->validated('payment_method', 'CASH'),
            sendWhatsAppInstructions: (bool) $request->validated('send_whatsapp_instructions', true)
        );

        $result = $action->execute($input);

        return ApiResponse::created(
            data: $result->toArray(),
            message: 'Intención de pago comercial generada exitosamente'
        );
    }

    public function handleWebhook(string $gateway, Request $request, ProcessPaymentWebhookAction $action): JsonResponse
    {
        $input = new ProcessPaymentWebhookInput(
            gateway: strtoupper($gateway),
            payload: $request->all(),
            headers: $request->headers->all(),
            signature: $request->header('X-Signature') ?? $request->header('Stripe-Signature')
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Webhook de pasarela de pago procesado correctamente'
        );
    }
}
