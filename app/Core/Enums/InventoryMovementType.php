<?php

namespace App\Core\Enums;

enum InventoryMovementType: string
{
    case STOCK_IN = 'STOCK_IN';
    case STOCK_OUT = 'STOCK_OUT';
    case ADJUSTMENT = 'ADJUSTMENT';
    case RESERVATION = 'RESERVATION';
}
