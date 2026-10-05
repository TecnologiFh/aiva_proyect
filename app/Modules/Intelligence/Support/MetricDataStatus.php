<?php

namespace App\Modules\Intelligence\Support;

enum MetricDataStatus: string
{
    case KNOWN = 'KNOWN';
    case NO_DATA = 'NO_DATA';
    case INSUFFICIENT_DATA = 'INSUFFICIENT_DATA';
    case DATA_CONFLICT = 'DATA_CONFLICT';
}
