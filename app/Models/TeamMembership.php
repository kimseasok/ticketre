<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamMembership extends Pivot
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    public const ROLE_LEAD = 'lead';
    public const ROLE_MEMBER = 'member';

    public const ROLES = [
        self::ROLE_LEAD,
        self::ROLE_MEMBER,
    ];

    public $incrementing = true;

    protected $table = 'team_memberships';

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'team_id',
        'user_id',
        'role',
        'is_primary',
        'joined_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'joined_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
