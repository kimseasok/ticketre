<?php

namespace App\Models;

use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Ticket;
use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketSubmission extends Model
{
    /** @use HasFactory<\Database\Factories\TicketSubmissionFactory> */
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILED = 'failed';

    public const CHANNEL_PORTAL = 'portal';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_CHAT = 'chat';
    public const CHANNEL_API = 'api';

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'ticket_id',
        'contact_id',
        'message_id',
        'channel',
        'status',
        'subject',
        'message',
        'tags',
        'metadata',
        'correlation_id',
        'submitted_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function messageRecord(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
