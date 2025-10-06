<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

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

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_INTERNAL = 'internal';

    public const ROLE_AGENT = 'agent';
    public const ROLE_CONTACT = 'contact';
    public const ROLE_SYSTEM = 'system';

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', self::VISIBILITY_PUBLIC);
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
}
