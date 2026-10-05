<?php

namespace App\Modules\Identity\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_owner_uuid' => ['required', 'string', 'uuid'],
            'previous_owner_new_role' => ['nullable', 'string', 'in:ADMIN,EMPLOYEE,SUPPORT'],
        ];
    }
}
