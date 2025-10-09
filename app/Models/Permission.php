<?php

namespace App\Models;

use App\Models\Brand;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasFactory;
    use BelongsToTenant;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'brand_id',
        'name',
        'slug',
        'description',
        'guard_name',
        'is_system',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_system' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Permission $permission): void {
            if (empty($permission->guard_name)) {
                $permission->guard_name = 'web';
            }

            if (empty($permission->slug)) {
                $permission->slug = Str::slug($permission->name ?: Str::uuid()->toString());
            }
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function roles(): BelongsToMany
    {
        /** @var BelongsToMany $relation */
        $relation = $this->belongsToMany(
            config('permission.models.role'),
            config('permission.table_names.role_has_permissions'),
            config('permission.column_names.permission_pivot_key', 'permission_id'),
            config('permission.column_names.role_pivot_key', 'role_id'),
        );

        return $relation;
    }

    public function scopeForBrand(Builder $query, ?Brand $brand): Builder
    {
        if (! $brand) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($brand): void {
            $builder
                ->whereNull('brand_id')
                ->orWhere('brand_id', $brand->getKey());
        });
    }
}
