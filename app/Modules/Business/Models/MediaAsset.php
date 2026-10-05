<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaAsset extends Model
{
    protected $table = 'media_assets';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'commercial_media_id',
        'lineage_id',
        'storage_key',
        'file_path',
        'mime_type',
        'media_type',
        'category',
        'title',
        'sha256',
        'catalog_version_id',
        'display_order',
        'revision_number',
        'status',
        'is_dispatchable',
    ];

    protected $casts = [
        'is_dispatchable' => 'boolean',
        'display_order' => 'integer',
        'revision_number' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->lineage_id)) {
                $model->lineage_id = (string) Str::uuid();
            }
            if (empty($model->storage_key) && !empty($model->file_path)) {
                $model->storage_key = $model->file_path;
            }
        });
    }

    public function regions()
    {
        return $this->hasMany(MediaVisualRegion::class, 'media_asset_id')->orderBy('reading_order', 'asc');
    }

    public function observations()
    {
        return $this->hasMany(MediaVisualObservation::class, 'media_asset_id');
    }

    public function entities()
    {
        return $this->hasMany(MediaVisualEntity::class, 'media_asset_id');
    }

    public function relations()
    {
        return $this->hasMany(MediaVisualRelation::class, 'media_asset_id');
    }

    public function semanticIndex()
    {
        return $this->hasMany(MediaSemanticIndex::class, 'media_asset_id');
    }

    public function commercialMedia()
    {
        return $this->belongsTo(CommercialMedia::class, 'commercial_media_id');
    }

    public function catalogVersion()
    {
        return $this->belongsTo(CatalogVersion::class, 'catalog_version_id');
    }

    /**
     * Condición Derivada DISPATCHABLE:
     * status ACTIVE + is_dispatchable true + archivo físico existente e íntegro
     */
    public function checkIsDispatchable(): bool
    {
        if ($this->status !== 'ACTIVE' || !$this->is_dispatchable) {
            return false;
        }

        $p = $this->file_path;
        $resolved = null;
        if ($p && file_exists($p)) {
            $resolved = $p;
        } elseif ($p && file_exists(public_path($p))) {
            $resolved = public_path($p);
        } elseif ($p && file_exists(storage_path('app/' . $p))) {
            $resolved = storage_path('app/' . $p);
        }

        if (!$resolved || is_dir($resolved)) {
            return false;
        }

        // Validación Cuádruple de Integridad Física Constitucional
        $size = @filesize($resolved);
        if ($size === false || $size <= 0) {
            return false;
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $mime = @mime_content_type($resolved);
        if (!$mime || !in_array($mime, $allowedMimes)) {
            return false;
        }

        if (!empty($this->sha256)) {
            $currentHash = @hash_file('sha256', $resolved);
            if ($currentHash !== $this->sha256) {
                return false;
            }
        }

        return true;
    }
}
