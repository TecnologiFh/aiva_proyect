<?php

namespace App\Core\Enums;

enum AutomationActionType: string
{
    case SEND_WHATSAPP = 'SEND_WHATSAPP';
    case ADD_CUSTOMER_TAG = 'ADD_CUSTOMER_TAG';
    case SEND_ADMIN_ALERT = 'SEND_ADMIN_ALERT';
    case GENERATE_COUPON = 'GENERATE_COUPON';
    case SEND_WEBHOOK = 'SEND_WEBHOOK';
}
