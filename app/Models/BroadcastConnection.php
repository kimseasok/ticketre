<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BroadcastConnection extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_STALE = 'stale';
    public const STATUS_DISCONNECTED = 'disconnected';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_STALE,
        self::STATUS_DISCONNECTED,
    ];

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'user_id',
        'connection_id',
        'channel_name',
        'status',
        'latency_ms',
        'last_seen_at',
        'metadata',
        'correlation_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
