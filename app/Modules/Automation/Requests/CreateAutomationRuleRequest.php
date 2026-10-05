<?php

namespace App\Modules\Automation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateAutomationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'trigger_name' => ['required', 'string', 'in:ORDER_CREATED,PAYMENT_APPROVED,LOW_STOCK_DETECTED,OUT_OF_STOCK_DETECTED,CUSTOMER_INACTIVE_30D'],
            'trigger_type' => ['nullable', 'string', 'in:EVENT,SCHEDULE'],
            'cron_expression' => ['nullable', 'string', 'max:50'],
            'logic_tree' => ['required', 'array'],
            'logic_tree.operator' => ['required', 'string', 'in:AND,OR,NOT'],
            'logic_tree.rules' => ['required', 'array'],
            'actions' => ['required', 'array'],
            'actions.*.action_type' => ['required', 'string', 'in:SEND_WHATSAPP,ADD_CUSTOMER_TAG,SEND_ADMIN_ALERT,GENERATE_COUPON,SEND_WEBHOOK'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:100'],
            'stop_processing' => ['nullable', 'boolean'],
        ];
    }
}
