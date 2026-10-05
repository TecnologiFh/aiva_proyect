<?php

namespace App\Core\Enums;

enum ConversationMode: string
{
    case AI = 'AI';
    case HUMAN = 'HUMAN';
    case HYBRID = 'HYBRID';
}
