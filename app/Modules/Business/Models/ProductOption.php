<?php

namespace App\Modules\Business\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOption extends Model
{
    use HasBinaryUuid, BelongsToTenant;

    protected $table = 'product_options';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'product_id',
        'name',
        'is_required',
        'min_selectable',
        'max_selectable',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'min_selectable' => 'integer',
        'max_selectable' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class, 'option_id');
    }
}
