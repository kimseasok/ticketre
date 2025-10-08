<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketWorkflow extends Model
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
        'description',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function states(): HasMany
    {
        return $this->hasMany(TicketWorkflowState::class)->orderBy('position');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(TicketWorkflowTransition::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function initialState(): ?TicketWorkflowState
    {
        return $this->states()->where('is_initial', true)->first();
    }
}
