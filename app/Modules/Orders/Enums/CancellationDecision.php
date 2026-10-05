<?php

namespace App\Modules\Orders\Enums;

enum CancellationDecision: string
{
    case CANCELLED_SUCCESS = 'CANCELLED_SUCCESS';
    case ALREADY_CANCELLED = 'ALREADY_CANCELLED';
    case CANCELLED_REFUND_REVIEW = 'CANCELLED_REFUND_REVIEW';
    case HUMAN_REVIEW_REQUIRED = 'HUMAN_REVIEW_REQUIRED';
    case DENIED_ORDER_FINISHED = 'DENIED_ORDER_FINISHED';
    case DENIED_POLICY = 'DENIED_POLICY';
    case ORDER_NOT_FOUND = 'ORDER_NOT_FOUND';
    case CONCURRENT_OPERATION_RETRY = 'CONCURRENT_OPERATION_RETRY';

    public function isSuccess(): bool
    {
        return in_array($this, [
            self::CANCELLED_SUCCESS,
            self::ALREADY_CANCELLED,
            self::CANCELLED_REFUND_REVIEW,
        ], true);
    }
}
