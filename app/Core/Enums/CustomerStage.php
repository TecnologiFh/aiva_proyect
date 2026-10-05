<?php

namespace App\Core\Enums;

enum CustomerStage: string
{
    case NEW = 'NEW';
    case ACTIVE = 'ACTIVE';
    case LOYAL = 'LOYAL';
    case VIP = 'VIP';
    case INACTIVE = 'INACTIVE';
    case LOST = 'LOST';
}
