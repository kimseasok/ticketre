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
    ];

    protected $casts = [
        'metadata' => 'array',
        'custom_fields' => 'array',
        'sla_due_at' => 'datetime',
    ];

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
