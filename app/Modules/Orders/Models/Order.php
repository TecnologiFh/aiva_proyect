<?php

namespace App\Modules\Orders\Models;

use App\Core\Enums\OrderStatus;
use App\Core\Enums\OrderType;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory, HasBinaryUuid, BelongsToTenant;

    protected $table = 'orders';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'conversation_id',
        'order_number',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'order_type',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'delivery_fee',
        'discount_amount',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'order_type' => OrderType::class,
        'status' => OrderStatus::class,
        'payment_status' => PaymentStatus::class,
        'payment_method' => PaymentMethod::class,
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }
}
