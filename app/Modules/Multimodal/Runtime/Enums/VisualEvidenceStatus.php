<?php

namespace App\Modules\Multimodal\Runtime\Enums;

enum VisualEvidenceStatus: string
{
    case RECEIVED = 'RECEIVED';
    case STRUCTURED = 'STRUCTURED';
    case PARTIAL = 'PARTIAL';
    case CORRUPTED = 'CORRUPTED';
    case PROCESSED = 'PROCESSED';
    case IDEMPOTENT_DUPLICATE = 'IDEMPOTENT_DUPLICATE';
    case REJECTED = 'REJECTED';
}
