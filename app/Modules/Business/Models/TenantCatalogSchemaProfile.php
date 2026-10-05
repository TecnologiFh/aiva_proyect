<?php

namespace App\Modules\Business\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TenantCatalogSchemaProfile extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_catalog_schema_profiles';

    // Frozen Taxonomy for AG-46 Schema Drift
    public const DRIFT_NO_CHANGE = 'NO_CHANGE';
    public const DRIFT_COMPATIBLE_EXTENSION = 'COMPATIBLE_EXTENSION';
    public const DRIFT_MAPPING_REQUIRES_REVIEW = 'MAPPING_REQUIRES_REVIEW';
    public const DRIFT_BREAKING_CHANGE = 'BREAKING_CHANGE';

    // Validation statuses
    public const STATUS_VALID = 'VALID';
    public const STATUS_REQUIRES_REVIEW = 'REQUIRES_REVIEW';
    public const STATUS_DRIFT_DETECTED = 'DRIFT_DETECTED';
    public const STATUS_BREAKING_CHANGE = 'BREAKING_CHANGE';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'profile_name',
        'source_type',
        'schema_signature',
        'header_row_index',
        'detected_columns',
        'column_mappings',
        'unmapped_columns',
        'validation_status',
        'drift_category',
        'version',
        'is_active',
    ];

    protected $casts = [
        'detected_columns' => 'array',
        'column_mappings' => 'array',
        'unmapped_columns' => 'array',
        'header_row_index' => 'integer',
        'version' => 'integer',
        'is_active' => 'boolean',
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

    public function canonicalItems(): HasMany
    {
        return $this->hasMany(CanonicalCatalogItem::class, 'schema_profile_id');
    }
}
