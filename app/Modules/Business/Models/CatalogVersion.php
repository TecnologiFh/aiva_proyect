<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogVersion extends Model
{
    protected $table = 'catalog_versions';

    public const MODE_FULL_SNAPSHOT = 'FULL_SNAPSHOT';
    public const MODE_INCREMENTAL = 'INCREMENTAL';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';
    public const STATUS_SUPERSEDED = 'SUPERSEDED';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    protected $fillable = [
        'tenant_id',
        'version_number',
        'source_file',
        'source_file_hash',
        'schema_profile_id',
        'import_mode',
        'changelog_summary',
        'published_items_count',
        'published_by',
        'version_snapshot',
        'status',
        'is_active',
    ];

    protected $casts = [
        'version_snapshot' => 'array',
        'is_active' => 'boolean',
        'version_number' => 'integer',
        'published_items_count' => 'integer',
    ];
}
