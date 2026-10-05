<?php

namespace App\Modules\Inventory\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use App\Modules\Business\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryStock extends Model
{
    use HasBinaryUuid, BelongsToTenant;

    protected $table = 'inventory_stocks';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'branch_id',
        'product_id',
        'current_stock',
        'reserved_stock',
        'minimum_stock',
        'track_inventory',
        'is_out_of_stock',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'current_stock' => 'float',
        'reserved_stock' => 'float',
        'minimum_stock' => 'float',
        'track_inventory' => 'boolean',
        'is_out_of_stock' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'stock_id');
    }

    public function getAvailableStockAttribute(): float
    {
        return max(0.00, $this->current_stock - $this->reserved_stock);
    }
}
