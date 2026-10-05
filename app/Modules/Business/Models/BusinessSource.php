<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSource extends Model
{
    protected $table = 'business_sources';

    protected $fillable = [
        'tenant_id',
        'source_type',
        'title',
        'file_path',
        'external_url',
        'raw_content',
        'mime_type',
        'file_size_bytes',
        'status',
        'error_log',
    ];
}
