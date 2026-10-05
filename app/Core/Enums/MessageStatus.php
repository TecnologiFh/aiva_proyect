<?php

namespace App\Core\Enums;

enum MessageStatus: string
{
    case RECEIVED = 'RECEIVED';
    case SENT = 'SENT';
    case DELIVERED = 'DELIVERED';
    case READ = 'READ';
    case FAILED = 'FAILED';
}
