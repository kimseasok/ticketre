<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound('currentTenant') && app('currentTenant')) {
            $builder->where($model->getTable().'.tenant_id', app('currentTenant')->getKey());
        }
    }
}

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model) {
            if (app()->bound('currentTenant') && app('currentTenant') && empty($model->tenant_id)) {
                $model->tenant_id = app('currentTenant')->getKey();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
