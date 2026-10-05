<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Commerce\Models\ProductVariant;

class ProductVisualReference extends Model
{
    protected $table = 'product_visual_references';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'product_id',
        'variant_id',
        'commercial_media_id',
        'evidence_id',
        'bbox_x',
        'bbox_y',
        'bbox_w',
        'bbox_h',
        'visual_label',
        'display_index',
        'confidence',
        'status',
    ];

    protected $casts = [
        'bbox_x' => 'float',
        'bbox_y' => 'float',
        'bbox_w' => 'float',
        'bbox_h' => 'float',
        'confidence' => 'float',
        'display_index' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            // Enforce XOR constraint (Rule 5)
            $hasProduct = !empty($model->product_id);
            $hasVariant = !empty($model->variant_id);
            if (($hasProduct && $hasVariant) || (!$hasProduct && !$hasVariant)) {
                throw new \InvalidArgumentException("Anti-Orphan XOR Constraint Violated: ProductVisualReference must have either product_id or variant_id, never both or neither.");
            }
            // Enforce non-null evidence_id and commercial_media_id (Rule 1)
            if (empty($model->evidence_id) || empty($model->commercial_media_id)) {
                throw new \InvalidArgumentException("Rule 1 Violated: ProductVisualReference requires evidence_id and commercial_media_id strictly NOT NULL.");
            }
            // Non-Negotiable C10: Tenant-Bound Referential Integrity
            $tenantId = (int)$model->tenant_id;
            if (!empty($model->product_id)) {
                $p = Product::find($model->product_id);
                if ($p && (int)$p->tenant_id !== $tenantId) {
                    throw new \InvalidArgumentException("C10 Violated: product.tenant_id ({$p->tenant_id}) !== pvr.tenant_id ({$tenantId}).");
                }
            }
            if (!empty($model->variant_id)) {
                $v = ProductVariant::find($model->variant_id);
                if ($v) {
                    if ((int)$v->tenant_id !== $tenantId) {
                        throw new \InvalidArgumentException("C10 Violated: variant.tenant_id ({$v->tenant_id}) !== pvr.tenant_id ({$tenantId}).");
                    }
                    if (!empty($model->product_id) && (int)$v->product_id !== (int)$model->product_id) {
                        throw new \InvalidArgumentException("C10 Violated: variant.product_id ({$v->product_id}) !== pvr.product_id ({$model->product_id}).");
                    }
                }
            }
            if (!empty($model->commercial_media_id)) {
                $m = CommercialMedia::find($model->commercial_media_id);
                if ($m && (int)$m->tenant_id !== $tenantId) {
                    throw new \InvalidArgumentException("C10 Violated: commercial_media.tenant_id ({$m->tenant_id}) !== pvr.tenant_id ({$tenantId}).");
                }
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function commercialMedia()
    {
        return $this->belongsTo(CommercialMedia::class, 'commercial_media_id');
    }
}
