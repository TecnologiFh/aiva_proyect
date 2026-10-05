<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogVersionMedia extends Model
{
    protected $table = 'catalog_version_media';
    public $timestamps = false;

    protected $fillable = [
        'catalog_version_id',
        'commercial_media_id',
        'tenant_id',
        'created_at',
    ];

    public function catalogVersion()
    {
        return $this->belongsTo(CatalogVersion::class, 'catalog_version_id');
    }

    public function commercialMedia()
    {
        return $this->belongsTo(CommercialMedia::class, 'commercial_media_id');
    }
}
