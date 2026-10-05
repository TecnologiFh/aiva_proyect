<?php

namespace App\Modules\Tenant\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantSetting extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'tenant_settings';

    protected $fillable = [
        'tenant_id',
        'setting_key',
        'setting_value',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'setting_value' => 'array',
        'version' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
