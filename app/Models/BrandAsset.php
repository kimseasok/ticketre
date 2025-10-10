<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BrandAsset extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'type',
        'disk',
        'path',
        'version',
        'content_type',
        'size',
        'checksum',
        'meta',
        'cache_control',
        'cdn_url',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function pathDigest(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return hash('sha256', $this->path);
    }

    public function etag(): string
    {
        $seed = implode('|', [
            $this->getKey(),
            $this->version,
            $this->checksum ?? '',
            $this->updated_at?->timestamp ?? 0,
        ]);

        return Str::substr(hash('sha256', $seed), 0, 32);
    }
}
