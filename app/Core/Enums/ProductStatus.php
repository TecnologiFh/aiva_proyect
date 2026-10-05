<?php

namespace App\Core\Enums;

enum ProductStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case OUT_OF_STOCK = 'OUT_OF_STOCK';
}
