<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketRelationship extends Model
{
    use HasFactory;
    use BelongsToTenant;
    use BelongsToBrand;

    public const TYPE_MERGE = 'merge';
    public const TYPE_SPLIT = 'split';
    public const TYPE_DUPLICATE = 'duplicate';

    /**
     * @var array<int, string>
     */
    public const TYPES = [
        self::TYPE_MERGE,
        self::TYPE_SPLIT,
        self::TYPE_DUPLICATE,
    ];

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'primary_ticket_id',
        'related_ticket_id',
        'relationship_type',
        'created_by',
        'context',
        'correlation_id',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function primaryTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'primary_ticket_id');
    }

    public function relatedTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'related_ticket_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
