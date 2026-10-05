<?php

namespace App\Modules\Orders\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_name' => ['required', 'string', 'max:150'],
            'delivery_address' => ['nullable', 'string'],
            'order_type' => ['nullable', 'string', 'in:DELIVERY,PICKUP,DINE_IN'],
            'conversation_id' => ['nullable', 'integer'],
            'entities' => ['required', 'array'],
            'entities.*.product' => ['required', 'string'],
            'entities.*.quantity' => ['nullable', 'integer', 'min:1'],
            'send_whatsapp_summary' => ['nullable', 'boolean'],
        ];
    }
}
