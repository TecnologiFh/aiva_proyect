<?php

namespace App\Modules\Inventory\Models;

use App\Core\Enums\InventoryMovementType;
use App\Core\Enums\MovementReason;
use App\Core\Enums\PerformedByType;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasBinaryUuid, BelongsToTenant;

    protected $table = 'inventory_movements';

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'stock_id',
        'movement_type',
        'movement_reason',
        'quantity',
        'previous_stock',
        'new_stock',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'performed_by_type',
        'performed_by_id',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'movement_type' => InventoryMovementType::class,
        'movement_reason' => MovementReason::class,
        'performed_by_type' => PerformedByType::class,
        'quantity' => 'float',
        'previous_stock' => 'float',
        'new_stock' => 'float',
        'unit_cost' => 'float',
        'total_cost' => 'float',
        'created_at' => 'datetime',
    ];
}
