<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;
    use BelongsToTenant;

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
        static::saving(function (Role $role) {
            if (empty($role->guard_name)) {
                $role->guard_name = 'web';
            }

            if (empty($role->slug)) {
                $role->slug = Str::slug($role->name ?: Str::uuid()->toString());
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function users(): BelongsToMany
    {
        /** @var BelongsToMany $relation */
        $relation = $this->morphedByMany(User::class, 'model', config('permission.table_names.model_has_roles'));

        return $relation;
    }
}
