<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketWorkflowTransition extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'ticket_workflow_id',
        'from_state_id',
        'to_state_id',
        'guard_hook',
        'requires_comment',
        'metadata',
    ];

    protected $casts = [
        'requires_comment' => 'boolean',
        'metadata' => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(TicketWorkflow::class, 'ticket_workflow_id');
    }

    public function fromState(): BelongsTo
    {
        return $this->belongsTo(TicketWorkflowState::class, 'from_state_id');
    }

    public function toState(): BelongsTo
    {
        return $this->belongsTo(TicketWorkflowState::class, 'to_state_id');
    }
}
