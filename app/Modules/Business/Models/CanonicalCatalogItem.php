<?php

namespace App\Modules\Business\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CanonicalCatalogItem extends Model
{
    use BelongsToTenant;

    protected $table = 'canonical_catalog_items';

    public const PRICE_CONFIRMED = 'CONFIRMED';
    public const PRICE_REVIEW_REQUIRED = 'REVIEW_REQUIRED';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'catalog_version_id',
        'schema_profile_id',
        'sku',
        'name',
        'normalized_name',
        'category',
        'description',
        'base_price',
        'price_status',
        'compatibility_json',
        'attributes_json',
        'stock',
        'stock_status',
        'provenance_json',
        'status',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'stock' => 'integer',
        'compatibility_json' => 'array',
        'attributes_json' => 'array',
        'provenance_json' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->normalized_name)) {
                $model->normalized_name = Str::of($model->name)->lower()->ascii()->trim()->squish()->toString();
            }
        });
    }

    public function schemaProfile(): BelongsTo
    {
        return $this->belongsTo(TenantCatalogSchemaProfile::class, 'schema_profile_id');
    }
}
