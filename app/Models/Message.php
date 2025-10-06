<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use App\Traits\BrandScope;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_INTERNAL = 'internal';

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'ticket_id',
        'user_id',
        'author_role',
        'visibility',
        'body',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            $message->visibility ??= self::VISIBILITY_PUBLIC;
            $message->author_role = $message->resolveAuthorRole();
        });

        static::updating(function (self $message): void {
            if ($message->isDirty('user_id') || $message->author_role === null) {
                $message->author_role = $message->resolveAuthorRole();
            }
        });
    }

    public function scopeForTicket($query, Ticket $ticket)
    {
        return $query->where('ticket_id', $ticket->getKey());
    }

    public function scopeVisibleToPortal($query)
    {
        return $query->where('visibility', self::VISIBILITY_PUBLIC);
    }

    public function scopeWithAudience($query, string $audience)
    {
        return $audience === 'portal'
            ? $query->visibleToPortal()
            : $query;
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    private function resolveAuthorRole(): ?string
    {
        if ($this->user_id) {
            $user = $this->author()
                ->withoutGlobalScopes([
                    TenantScope::class,
                    BrandScope::class,
                ])
                ->first();

            return $user?->getRoleNames()->first();
        }

        return null;
    }
}
