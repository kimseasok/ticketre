<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketRelationship extends Model
{
    use HasFactory;
    use BelongsToTenant;
    use BelongsToBrand;

    public const TYPE_MERGED = 'merged';
    public const TYPE_SPLIT = 'split';
    public const TYPE_DUPLICATE = 'duplicate';

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'primary_ticket_id',
        'related_ticket_id',
        'relationship_type',
        'context',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public static function allowedTypes(): array
    {
        return [
            self::TYPE_MERGED,
            self::TYPE_SPLIT,
            self::TYPE_DUPLICATE,
        ];
    }

    public function primaryTicket()
    {
        return $this->belongsTo(Ticket::class, 'primary_ticket_id');
    }

    public function relatedTicket()
    {
        return $this->belongsTo(Ticket::class, 'related_ticket_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
