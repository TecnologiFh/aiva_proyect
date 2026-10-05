<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CommercialMedia extends Model
{
    protected $table = 'commercial_media';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'lineage_id',
        'media_type',
        'category',
        'title',
        'file_path',
        'content_hash',
        'revision_number',
        'previous_media_id',
        'mime_type',
        'file_size_bytes',
        'display_order',
        'status',
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
            // Non-Negotiable C11: Revision Lineage Enforcement
            if (!empty($model->revision_number) && $model->revision_number > 1) {
                if (empty($model->previous_media_id)) {
                    throw new \InvalidArgumentException("C11 Violated: Revision > 1 requires previous_media_id.");
                }
                $prev = static::find($model->previous_media_id);
                if (!$prev) {
                    throw new \InvalidArgumentException("C11 Violated: previous_media_id {$model->previous_media_id} not found.");
                }
                if ((int)$prev->tenant_id !== (int)$model->tenant_id) {
                    throw new \InvalidArgumentException("C11 Violated: previous.tenant_id ({$prev->tenant_id}) !== new.tenant_id ({$model->tenant_id}).");
                }
                if ($prev->lineage_id !== $model->lineage_id) {
                    throw new \InvalidArgumentException("C11 Violated: previous.lineage_id ({$prev->lineage_id}) !== new.lineage_id ({$model->lineage_id}).");
                }
                if ((int)$model->revision_number !== ((int)$prev->revision_number + 1)) {
                    throw new \InvalidArgumentException("C11 Violated: Non-sequential revision! Expected " . ($prev->revision_number + 1) . ", got {$model->revision_number}.");
                }
                if ($model->content_hash && $prev->content_hash && $model->content_hash === $prev->content_hash) {
                    throw new \InvalidArgumentException("C11 Violated: Content hash identical to previous revision, new revision rejected.");
                }
            }
        });
    }

    /**
     * Registro con Idempotencia por Hash y Serialización Transaccional (C11).
     * Si el hash es idéntico a una revisión existente en el linaje, la reutiliza.
     * Si el hash es diferente, crea atómicamente la revisión sequential +1 con lock.
     */
    public static function registerOrReuse(array $attributes): self
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($attributes) {
            $tenantId = $attributes['tenant_id'];
            $lineageId = $attributes['lineage_id'] ?? (string) Str::uuid();
            $contentHash = $attributes['content_hash'] ?? null;

            // 1. Bloqueo de lectura para prevenir carreras concurrentes
            if ($contentHash) {
                $existing = static::where('tenant_id', $tenantId)
                    ->where('lineage_id', $lineageId)
                    ->where('content_hash', $contentHash)
                    ->lockForUpdate()
                    ->first();

                // Idempotencia: Si el contenido no cambió, reutilizar registro existente sin error
                if ($existing) {
                    return $existing;
                }
            }

            // 2. Obtener última revisión bajo lock
            $prev = static::where('tenant_id', $tenantId)
                ->where('lineage_id', $lineageId)
                ->lockForUpdate()
                ->orderBy('revision_number', 'desc')
                ->first();

            $nextRev = $prev ? ($prev->revision_number + 1) : 1;
            $attributes['lineage_id'] = $lineageId;
            $attributes['revision_number'] = $nextRev;
            $attributes['previous_media_id'] = $prev ? $prev->id : null;

            return static::create($attributes);
        });
    }

    public function aliases()
    {
        return $this->hasMany(CommercialMediaAlias::class, 'commercial_media_id');
    }

    public function catalogVersions()
    {
        return $this->belongsToMany(CatalogVersion::class, 'catalog_version_media', 'commercial_media_id', 'catalog_version_id');
    }

    public function visualReferences()
    {
        return $this->hasMany(ProductVisualReference::class, 'commercial_media_id');
    }

    public function previousMedia()
    {
        return $this->belongsTo(CommercialMedia::class, 'previous_media_id');
    }
}
