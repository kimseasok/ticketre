<?php

namespace App\Models;

use App\Models\TicketDeletionRequest;
use App\Models\TicketEvent;
use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Ticket extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;
    use Searchable;

    public const CHANNEL_AGENT = 'agent';
    public const CHANNEL_PORTAL = 'portal';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_CHAT = 'chat';
    public const CHANNEL_API = 'api';

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'contact_id',
        'company_id',
        'assignee_id',
        'ticket_workflow_id',
        'sla_policy_id',
        'subject',
        'status',
        'priority',
        'channel',
        'department',
        'category',
        'workflow_state',
        'metadata',
        'custom_fields',
        'sla_due_at',
        'first_response_due_at',
        'resolution_due_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'custom_fields' => 'array',
        'sla_due_at' => 'datetime',
        'first_response_due_at' => 'datetime',
        'resolution_due_at' => 'datetime',
    ];

    public function slaPolicy()
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('sent_at')->orderBy('id');
    }

    public function workflow()
    {
        return $this->belongsTo(TicketWorkflow::class, 'ticket_workflow_id');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function events()
    {
        return $this->hasMany(TicketEvent::class);
    }

    public function deletionRequests(): HasMany
    {
        return $this->hasMany(TicketDeletionRequest::class);
    }

    public function primaryRelationships(): HasMany
    {
        return $this->hasMany(TicketRelationship::class, 'primary_ticket_id');
    }

    public function relatedRelationships(): HasMany
    {
        return $this->hasMany(TicketRelationship::class, 'related_ticket_id');
    }

    public function mergesAsPrimary(): HasMany
    {
        return $this->hasMany(TicketMerge::class, 'primary_ticket_id');
    }

    public function mergesAsSecondary(): HasMany
    {
        return $this->hasMany(TicketMerge::class, 'secondary_ticket_id');
    }

    public function toSearchableArray(): array
    {
        return [
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'category' => $this->category,
            'channel' => $this->channel,
        ];
    }
}
