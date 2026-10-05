<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;

class CommercialMediaAlias extends Model
{
    protected $table = 'commercial_media_aliases';

    protected $fillable = [
        'commercial_media_id',
        'tenant_id',
        'alias',
        'weight',
    ];

    public function commercialMedia()
    {
        return $this->belongsTo(CommercialMedia::class, 'commercial_media_id');
    }
}
