<?php

namespace App\Modules\Payments\Domain\Enums;

enum EvidenceStatus: string
{
    case RECEIVED = 'RECEIVED';
    case QUALITY_CHECKED = 'QUALITY_CHECKED';
    case CLASSIFIED = 'CLASSIFIED';
    case EXTRACTED = 'EXTRACTED';
    case VALIDATED = 'VALIDATED';
    case RISK_EVALUATED = 'RISK_EVALUATED';
    case AUTHORIZED = 'AUTHORIZED';
    case APPLIED = 'APPLIED';
    case PAID = 'PAID';

    // Exception & Branch States
    case QUALITY_REJECTED = 'QUALITY_REJECTED';
    case EXTRACTION_FAILED = 'EXTRACTION_FAILED';
    case VALIDATION_FAILED = 'VALIDATION_FAILED';
    case DUPLICATE_DETECTED = 'DUPLICATE_DETECTED';
    case MISMATCH = 'MISMATCH';
    case MANUAL_REVIEW = 'MANUAL_REVIEW';
    case EXPIRED = 'EXPIRED';
    case REJECTED = 'REJECTED';
    case PAYMENT_UNVERIFIED = 'PAYMENT_UNVERIFIED';
}
