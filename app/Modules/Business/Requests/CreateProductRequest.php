<?php

namespace App\Modules\Business\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_uuid' => ['required', 'string', 'uuid'],
            'name' => ['required', 'string', 'max:150'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'search_keywords' => ['nullable', 'string'],
            'product_type' => ['nullable', 'string', 'in:PRODUCT,SERVICE'],
            'sku' => ['nullable', 'string', 'max:100'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'options' => ['nullable', 'array'],
            'options.*.name' => ['required_with:options', 'string', 'max:100'],
            'options.*.is_required' => ['nullable', 'boolean'],
            'options.*.min_selectable' => ['nullable', 'integer', 'min:0'],
            'options.*.max_selectable' => ['nullable', 'integer', 'min:1'],
            'options.*.values' => ['required_with:options', 'array'],
            'options.*.values.*.name' => ['required', 'string', 'max:100'],
            'options.*.values.*.price_modifier' => ['nullable', 'numeric'],
            'options.*.values.*.is_default' => ['nullable', 'boolean'],
        ];
    }
}
