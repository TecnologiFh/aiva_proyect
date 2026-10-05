<?php

namespace App\Modules\AI\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessAIMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'conversation_id' => ['nullable', 'integer'],
            'recent_messages' => ['nullable', 'array'],
            'driver' => ['nullable', 'string', 'in:OPENAI,GEMINI,MOCK'],
        ];
    }
}
