<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'name',
        'slug',
        'default_queue',
        'description',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'brand_id' => 'integer',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_memberships')
            ->using(TeamMembership::class)
            ->withPivot(['role', 'is_primary'])
            ->withTimestamps();
    }
}
