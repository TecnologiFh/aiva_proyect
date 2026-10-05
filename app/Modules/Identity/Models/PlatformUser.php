<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class PlatformUser extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'platform_users';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'password',
        'role', // SUPERADMIN, SUPPORT, BILLING
        'status', // ACTIVE, SUSPENDED
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function isSuperAdmin(): bool
    {
        return strtolower($this->role) === 'superadmin' || strtolower($this->role) === 'super_admin';
    }
}
