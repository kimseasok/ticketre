<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ObservabilityPipeline extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'name',
        'slug',
        'pipeline_type',
        'ingest_endpoint',
        'ingest_protocol',
        'buffer_strategy',
        'buffer_retention_seconds',
        'retry_backoff_seconds',
        'max_retry_attempts',
        'batch_max_bytes',
        'metrics_scrape_interval_seconds',
        'metadata',
    ];

    protected $casts = [
        'buffer_retention_seconds' => 'integer',
        'retry_backoff_seconds' => 'integer',
        'max_retry_attempts' => 'integer',
        'batch_max_bytes' => 'integer',
        'metrics_scrape_interval_seconds' => 'integer',
        'metadata' => 'array',
    ];

    public function ingestEndpointDigest(): string
    {
        return hash('sha256', (string) $this->ingest_endpoint);
    }

    public function ingestEndpointPreview(): ?string
    {
        if (! $this->ingest_endpoint) {
            return null;
        }

        $sanitized = preg_replace('/[^A-Za-z0-9:\/._-]/', '', (string) $this->ingest_endpoint) ?? '';

        return $sanitized === '' ? null : substr($sanitized, 0, 32);
    }
}
