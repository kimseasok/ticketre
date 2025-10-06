<?php

namespace App\Models;

use App\Models\TicketEvent;
use App\Models\TicketRelationship;
use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Ticket extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;
    use Searchable;

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'contact_id',
        'company_id',
        'assignee_id',
        'subject',
        'status',
        'priority',
        'department',
        'category',
        'workflow_state',
        'metadata',
        'sla_due_at',
    ];

    protected $casts = [
        'metadata' => 'array',
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

    public function relationships()
    {
        return $this->hasMany(TicketRelationship::class, 'primary_ticket_id');
    }

    public function inverseRelationships()
    {
        return $this->hasMany(TicketRelationship::class, 'related_ticket_id');
    }

    public function toSearchableArray(): array
    {
        return [
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'category' => $this->category,
        ];
    }
}
