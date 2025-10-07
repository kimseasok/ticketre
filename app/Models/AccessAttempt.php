<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'user_id',
        'route',
        'permission',
        'granted',
        'reason',
        'correlation_id',
        'ip_hash',
        'user_agent_hash',
        'metadata',
    ];

    protected $casts = [
        'granted' => 'boolean',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
