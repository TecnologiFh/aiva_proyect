<?php

namespace App\Core\Enums;

enum ConversationStatus: string
{
    case OPEN = 'OPEN';
    case CLOSED = 'CLOSED';
    case ARCHIVED = 'ARCHIVED';
}
