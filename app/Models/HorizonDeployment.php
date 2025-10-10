<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HorizonDeployment extends Model
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
        'domain',
        'auth_guard',
        'horizon_connection',
        'uses_tls',
        'supervisors',
        'last_deployed_at',
        'ssl_certificate_expires_at',
        'last_health_status',
        'last_health_checked_at',
        'last_health_report',
        'metadata',
    ];

    protected $casts = [
        'uses_tls' => 'boolean',
        'supervisors' => 'array',
        'metadata' => 'array',
        'last_deployed_at' => 'datetime',
        'ssl_certificate_expires_at' => 'datetime',
        'last_health_checked_at' => 'datetime',
        'last_health_report' => 'array',
    ];

    public function domainDigest(): ?string
    {
        if (! $this->domain) {
            return null;
        }

        return hash('sha256', strtolower($this->domain));
    }

    public function supervisorCount(): int
    {
        return is_array($this->supervisors) ? count($this->supervisors) : 0;
    }
}
