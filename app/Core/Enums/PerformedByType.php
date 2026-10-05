<?php

namespace App\Core\Enums;

enum PerformedByType: string
{
    case USER = 'USER';
    case SYSTEM = 'SYSTEM';
    case AI = 'AI';
}
