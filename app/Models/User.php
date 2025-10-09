<?php

namespace App\Models;

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TwoFactorCredential;
use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;
    use HasRoles {
        assignRole as protected traitAssignRole;
        syncRoles as protected traitSyncRoles;
    }
    use BelongsToTenant;
    use BelongsToBrand;

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'name',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function assignRole(...$roles)
    {
        return $this->withTenantContext(fn () => $this->traitAssignRole(...$roles));
    }

    public function syncRoles(...$roles)
    {
        return $this->withTenantContext(fn () => $this->traitSyncRoles(...$roles));
    }

    protected function withTenantContext(callable $callback)
    {
        $hasTenant = app()->bound('currentTenant');
        $previousTenant = $hasTenant ? app('currentTenant') : null;

        $tenantId = $this->tenant_id;

        if (! $tenantId && $this->relationLoaded('tenant')) {
            $tenant = $this->getRelation('tenant');
            $tenantId = $tenant?->getKey();
        }

        $tenantModel = null;
        if ($tenantId) {
            $tenantModel = Tenant::withoutGlobalScopes()->find($tenantId);
        }

        if ($tenantModel) {
            app()->instance('currentTenant', $tenantModel);
        }

        try {
            return $callback();
        } finally {
            if ($hasTenant && $previousTenant) {
                app()->instance('currentTenant', $previousTenant);
            } else {
                app()->forgetInstance('currentTenant');
            }
        }
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'assignee_id');
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_memberships')
            ->using(TeamMembership::class)
            ->withPivot(['role', 'is_primary', 'joined_at'])
            ->withTimestamps();
    }

    public function twoFactorCredential(): HasOne
    {
        return $this->hasOne(TwoFactorCredential::class);
    }
}
