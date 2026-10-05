<?php

namespace App\Core\Enums;

enum SenderType: string
{
    case CUSTOMER = 'CUSTOMER';
    case AI = 'AI';
    case AGENT = 'AGENT';
}
