<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketMerge extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'primary_ticket_id',
        'secondary_ticket_id',
        'initiated_by',
        'status',
        'summary',
        'correlation_id',
        'completed_at',
        'failed_at',
        'failure_reason',
    ];

    protected $casts = [
        'summary' => 'array',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function primaryTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'primary_ticket_id');
    }

    public function secondaryTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'secondary_ticket_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
