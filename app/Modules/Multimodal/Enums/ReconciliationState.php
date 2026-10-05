<?php

namespace App\Modules\Multimodal\Enums;

enum ReconciliationState: string
{
    case MATCH_CANONICAL = 'MATCH_CANONICAL';
    case VISUAL_ONLY = 'VISUAL_ONLY';
    case AMBIGUOUS_CONFLICT = 'AMBIGUOUS_CONFLICT';
    case UNRESOLVED_INSUFFICIENT = 'UNRESOLVED_INSUFFICIENT';
}
