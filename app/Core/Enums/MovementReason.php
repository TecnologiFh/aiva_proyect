<?php

namespace App\Core\Enums;

enum MovementReason: string
{
    case SALE = 'SALE';
    case PURCHASE = 'PURCHASE';
    case WASTE = 'WASTE';
    case DAMAGE = 'DAMAGE';
    case EXPIRED = 'EXPIRED';
    case RETURN = 'RETURN';
    case TRANSFER = 'TRANSFER';
    case RESERVATION = 'RESERVATION';
}
