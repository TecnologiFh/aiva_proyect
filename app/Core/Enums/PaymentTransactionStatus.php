<?php

namespace App\Core\Enums;

enum PaymentTransactionStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case EXPIRED = 'EXPIRED';
    case REFUNDED = 'REFUNDED';
}
