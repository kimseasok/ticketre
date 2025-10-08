<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\BelongsToBrand;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketWorkflowState extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'ticket_workflow_id',
        'name',
        'slug',
        'position',
        'is_initial',
        'is_terminal',
        'sla_minutes',
        'entry_hook',
        'description',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_initial' => 'boolean',
        'is_terminal' => 'boolean',
        'sla_minutes' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(TicketWorkflow::class, 'ticket_workflow_id');
    }

    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(TicketWorkflowTransition::class, 'from_state_id');
    }

    public function incomingTransitions(): HasMany
    {
        return $this->hasMany(TicketWorkflowTransition::class, 'to_state_id');
    }
}
