<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ObservabilityStack extends Model
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
        'status',
        'logs_tool',
        'metrics_tool',
        'alerts_tool',
        'log_retention_days',
        'metric_retention_days',
        'trace_retention_days',
        'estimated_monthly_cost',
        'trace_sampling_strategy',
        'decision_matrix',
        'security_notes',
        'compliance_notes',
        'metadata',
    ];

    protected $casts = [
        'log_retention_days' => 'integer',
        'metric_retention_days' => 'integer',
        'trace_retention_days' => 'integer',
        'estimated_monthly_cost' => 'decimal:2',
        'decision_matrix' => 'array',
        'metadata' => 'array',
    ];

    public function nameDigest(): string
    {
        return hash('sha256', (string) $this->name);
    }

    public function logsToolDigest(): string
    {
        return hash('sha256', (string) $this->logs_tool);
    }

    public function metricsToolDigest(): string
    {
        return hash('sha256', (string) $this->metrics_tool);
    }
}
