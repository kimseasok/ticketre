<?php

namespace App\Models;

use App\Models\TicketCategory;
use App\Models\TicketDeletionRequest;
use App\Models\TicketDepartment;
use App\Models\TicketEvent;
use App\Models\TicketTag;
use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use App\Traits\BrandScope;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'department_id',
        'subject',
        'status',
        'priority',
        'channel',
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

    public function departmentRelation(): BelongsTo
    {
        return $this->belongsTo(TicketDepartment::class, 'department_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(TicketCategory::class, 'ticket_category_ticket')->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TicketTag::class, 'ticket_tag_ticket')->withTimestamps();
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
        $this->loadMissing(['categories', 'tags', 'departmentRelation']);

        return [
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'category' => $this->category,
            'channel' => $this->channel,
            'categories' => $this->categories->pluck('name')->all(),
            'tags' => $this->tags->pluck('name')->all(),
            'department' => $this->departmentRelation?->name,
        ];
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery(
            $query->withoutGlobalScopes([TenantScope::class, BrandScope::class]),
            $value,
            $field
        );
    }
}
