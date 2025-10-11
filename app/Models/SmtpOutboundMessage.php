<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $tenant_id
 * @property int $brand_id
 * @property int $ticket_id
 * @property int|null $message_id
 * @property string $status
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $failed_at
 * @property-read \App\Models\Tenant|null $tenant
 * @property-read \App\Models\Brand|null $brand
 * @property-read \App\Models\Ticket|null $ticket
 * @property-read \App\Models\Message|null $message
 */
class SmtpOutboundMessage extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_RETRYING = 'retrying';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'ticket_id',
        'message_id',
        'status',
        'mailer',
        'subject',
        'from_email',
        'from_name',
        'to',
        'cc',
        'bcc',
        'reply_to',
        'headers',
        'attachments',
        'body_html',
        'body_text',
        'attempts',
        'queued_at',
        'dispatched_at',
        'delivered_at',
        'failed_at',
        'last_error',
        'correlation_id',
    ];

    protected $casts = [
        'to' => 'array',
        'cc' => 'array',
        'bcc' => 'array',
        'reply_to' => 'array',
        'headers' => 'array',
        'attachments' => 'array',
        'queued_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_FAILED], true);
    }

    /**
     * @return array<int, string>
     */
    public function recipientDigests(): array
    {
        $addresses = array_merge(
            $this->addressEmails($this->to ?? []),
            $this->addressEmails($this->cc ?? []),
            $this->addressEmails($this->bcc ?? [])
        );

        return array_map(
            static fn (string $email): string => hash('sha256', strtolower($email)),
            $addresses
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $addresses
     * @return array<int, string>
     */
    protected function addressEmails(array $addresses): array
    {
        return array_values(array_filter(array_map(
            static fn (array $address): ?string => isset($address['email']) ? (string) $address['email'] : null,
            $addresses
        )));
    }
}
