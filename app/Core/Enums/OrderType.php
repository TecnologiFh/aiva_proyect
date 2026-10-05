<?php

namespace App\Core\Enums;

enum OrderType: string
{
    case DELIVERY = 'DELIVERY';
    case PICKUP = 'PICKUP';
    case DINE_IN = 'DINE_IN';
}
