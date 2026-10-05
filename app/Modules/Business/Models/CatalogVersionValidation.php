<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CatalogVersionValidation extends Model
{
    protected $table = 'catalog_version_validations';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'catalog_version_id',
        'commercial_media_id',
        'expected_sellable_count',
        'evidence_count',
        'resolved_count',
        'pvr_count',
        'missing_count',
        'missing_items_json',
        'status',
        'validated_at',
    ];

    protected $casts = [
        'missing_items_json' => 'array',
        'validated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function catalogVersion()
    {
        return $this->belongsTo(CatalogVersion::class, 'catalog_version_id');
    }

    public function commercialMedia()
    {
        return $this->belongsTo(CommercialMedia::class, 'commercial_media_id');
    }
}
