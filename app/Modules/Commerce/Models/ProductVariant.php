<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Business\Models\Product;

class ProductVariant extends Model
{
    protected $table = 'product_variants';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'product_id',
        'sku',
        'price_override',
        'color',
        'talla',
        'stock',
        'status',
    ];

    protected $casts = [
        'price_override' => 'float',
        'stock' => 'integer',
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

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withoutGlobalScopes();
    }

    public function attributes()
    {
        return $this->hasMany(ProductVariantAttribute::class, 'variant_id');
    }

    /**
     * Variant Price Authority (Rule 4):
     * effective_price = COALESCE(price_override, base_price)
     */
    public function getEffectivePriceAttribute(): float
    {
        if ($this->price_override !== null) {
            return (float) $this->price_override;
        }
        return (float) ($this->product ? $this->product->base_price : 0.00);
    }
}
