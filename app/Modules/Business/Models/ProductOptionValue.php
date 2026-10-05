<?php

namespace App\Modules\Business\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOptionValue extends Model
{
    use HasBinaryUuid, BelongsToTenant;

    protected $table = 'product_option_values';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'option_id',
        'name',
        'price_modifier',
        'is_default',
    ];

    protected $casts = [
        'price_modifier' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'option_id');
    }
}
