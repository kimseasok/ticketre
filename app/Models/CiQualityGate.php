<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CiQualityGate extends Model
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
        'coverage_threshold',
        'max_critical_vulnerabilities',
        'max_high_vulnerabilities',
        'enforce_dependency_audit',
        'enforce_docker_build',
        'notifications_enabled',
        'notify_channel',
        'metadata',
    ];

    protected $casts = [
        'coverage_threshold' => 'decimal:2',
        'max_critical_vulnerabilities' => 'integer',
        'max_high_vulnerabilities' => 'integer',
        'enforce_dependency_audit' => 'boolean',
        'enforce_docker_build' => 'boolean',
        'notifications_enabled' => 'boolean',
        'metadata' => 'array',
    ];

    public function notifyChannelDigest(): ?string
    {
        if (! $this->notify_channel) {
            return null;
        }

        return hash('sha256', (string) $this->notify_channel);
    }
}
