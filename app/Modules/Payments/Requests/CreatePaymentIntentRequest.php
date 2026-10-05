<?php

namespace App\Modules\Payments\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_uuid' => ['required', 'string', 'uuid'],
            'gateway' => ['nullable', 'string', 'in:MOCK,YAPE_PLIN,STRIPE,CULQI'],
            'payment_method' => ['nullable', 'string', 'in:CASH,YAPE_PLIN,CARD,ONLINE'],
            'send_whatsapp_instructions' => ['nullable', 'boolean'],
        ];
    }
}
