<?php

namespace App\Core\Enums;

enum RuleTriggerType: string
{
    case EVENT = 'EVENT';
    case SCHEDULE = 'SCHEDULE';
}
