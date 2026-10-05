<?php

namespace App\Modules\Multimodal\Runtime\Enums;

enum VisualResolutionStatus: string
{
    case MATCH_EXACT = 'MATCH_EXACT';
    case MATCH_CANONICAL = 'MATCH_CANONICAL';
    case CONFLICT = 'CONFLICT';
    case AMBIGUOUS = 'AMBIGUOUS';
    case NOT_FOUND = 'NOT_FOUND';
    case UNCONFIRMED_COMPATIBILITY = 'UNCONFIRMED_COMPATIBILITY';
    case STRUCTURED_ONLY = 'STRUCTURED_ONLY';
    case NON_COMMERCIAL = 'NON_COMMERCIAL';
}
