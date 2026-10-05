<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariantAttribute extends Model
{
    protected $table = 'product_variant_attributes';

    protected $fillable = [
        'variant_id',
        'tenant_id',
        'attribute_name',
        'attribute_value',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
