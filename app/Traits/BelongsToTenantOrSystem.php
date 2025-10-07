<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;

class TenantOrSystemScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('currentTenant')) {
            return;
        }

        $tenant = app('currentTenant');

        if (! $tenant) {
            return;
        }

        $builder->where(function (Builder $query) use ($model, $tenant) {
            $query->where($model->getTable().'.tenant_id', $tenant->getKey())
                ->orWhereNull($model->getTable().'.tenant_id');
        });
    }
}

trait BelongsToTenantOrSystem
{
    public static function bootBelongsToTenantOrSystem(): void
    {
        static::addGlobalScope(new TenantOrSystemScope());

        static::creating(function (Model $model) {
            if (app()->bound('currentTenant') && app('currentTenant') && empty($model->tenant_id)) {
                $model->tenant_id = app('currentTenant')->getKey();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
