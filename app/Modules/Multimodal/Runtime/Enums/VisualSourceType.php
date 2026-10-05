<?php

namespace App\Modules\Multimodal\Runtime\Enums;

enum VisualSourceType: string
{
    case WHATSAPP_INBOUND = 'WHATSAPP_INBOUND';
    case CATALOG_UPLOAD = 'CATALOG_UPLOAD';
    case API_INGESTION = 'API_INGESTION';
    case OCR_FALLBACK = 'OCR_FALLBACK';
}
