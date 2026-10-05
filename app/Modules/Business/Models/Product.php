<?php

namespace App\Modules\Business\Models;

use App\Core\Enums\ProductStatus;
use App\Core\Enums\ProductType;
use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasBinaryUuid, BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'catalog_version_id',
        'category_id',
        'name',
        'slug',
        'description',
        'search_keywords',
        'product_type',
        'sku',
        'base_price',
        'price_status',
        'image_path',
        'status',
        'is_available',
        'compatibility',
        'attributes',
        'stock',
        'stock_status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'stock' => 'integer',
        'compatibility' => 'array',
        'attributes' => 'array',
        'is_available' => 'boolean',
        'product_type' => ProductType::class,
        'status' => ProductStatus::class,
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class, 'product_id');
    }
}
