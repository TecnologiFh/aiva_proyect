<?php

namespace App\Modules\Tenant\Models;

use App\Core\Enums\TenantStatus;
use App\Core\Traits\Auditable;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasBinaryUuid, Auditable, SoftDeletes;

    protected $table = 'tenants';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'business_type',
        'country',
        'currency',
        'timezone',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => TenantStatus::class,
    ];

    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class, 'tenant_id');
    }
}
