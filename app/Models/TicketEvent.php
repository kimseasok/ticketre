<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Database\Factories\TicketEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEvent extends Model
{
    use HasFactory;
    use BelongsToTenant;
    use BelongsToBrand;

    public const TYPE_CREATED = 'ticket.created';
    public const TYPE_UPDATED = 'ticket.updated';
    public const TYPE_ASSIGNED = 'ticket.assigned';
    public const TYPE_MERGED = 'ticket.merged';

    public const VISIBILITY_INTERNAL = 'internal';
    public const VISIBILITY_PUBLIC = 'public';

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'ticket_id',
        'initiator_id',
        'type',
        'visibility',
        'correlation_id',
        'payload',
        'broadcasted_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'broadcasted_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    protected static function newFactory(): TicketEventFactory
    {
        return TicketEventFactory::new();
    }
}
