<?php

namespace App\Modules\AI\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
            'driver' => ['nullable', 'string', 'in:OPENAI,GEMINI,MOCK'],
        ];
    }
}
