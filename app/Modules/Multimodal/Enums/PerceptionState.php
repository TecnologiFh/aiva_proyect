<?php

namespace App\Modules\Multimodal\Enums;

enum PerceptionState: string
{
    case OBSERVED = 'OBSERVED';
    case STRUCTURED = 'STRUCTURED';
    case SEMANTICALLY_IDENTIFIED = 'SEMANTICALLY_IDENTIFIED';
    case INSUFFICIENT_EVIDENCE = 'INSUFFICIENT_EVIDENCE';
}
