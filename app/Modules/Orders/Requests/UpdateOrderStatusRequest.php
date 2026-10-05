<?php

namespace App\Modules\Orders\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:DRAFT,PENDING_CONFIRMATION,CONFIRMED,PREPARING,READY,DELIVERED,CANCELLED'],
            'notify_whatsapp' => ['nullable', 'boolean'],
        ];
    }
}
