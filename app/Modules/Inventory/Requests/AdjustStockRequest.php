<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'movement_type' => ['required', 'string', 'in:STOCK_IN,STOCK_OUT,ADJUSTMENT'],
            'movement_reason' => ['nullable', 'string', 'in:SALE,PURCHASE,WASTE,DAMAGE,EXPIRED,RETURN,TRANSFER,RESERVATION'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
