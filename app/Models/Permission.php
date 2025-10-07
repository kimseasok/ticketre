<?php

namespace App\Models;

use App\Traits\BelongsToTenantOrSystem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasFactory;
    use BelongsToTenantOrSystem;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'guard_name',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Permission $permission) {
            if (empty($permission->guard_name)) {
                $permission->guard_name = 'web';
            }

            if (empty($permission->slug)) {
                $permission->slug = Str::slug($permission->name ?: Str::uuid()->toString());
            }
        });
    }

    public function roles(): BelongsToMany
    {
        /** @var BelongsToMany $relation */
        $relation = $this->belongsToMany(Role::class, config('permission.table_names.role_has_permissions'));

        return $relation;
    }
}
