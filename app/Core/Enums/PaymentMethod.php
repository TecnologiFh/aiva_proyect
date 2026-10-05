<?php

namespace App\Core\Enums;

enum PaymentMethod: string
{
    case CASH = 'CASH';
    case YAPE_PLIN = 'YAPE_PLIN';
    case CARD = 'CARD';
    case ONLINE = 'ONLINE';
}
