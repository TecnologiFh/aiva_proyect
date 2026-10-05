<?php

namespace App\Modules\Multimodal\Enums;

enum TemporalStatus: string
{
    case VALID_CURRENT = 'VALID_CURRENT';
    case VALID_HISTORICAL = 'VALID_HISTORICAL';
    case STALE = 'STALE';
    case INVALIDATED = 'INVALIDATED';
}
