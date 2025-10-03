<?php

namespace App\Traits;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BrandScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound('currentBrand') && app('currentBrand')) {
            $builder->where($model->getTable().'.brand_id', app('currentBrand')->getKey());
        }
    }
}

trait BelongsToBrand
{
    public static function bootBelongsToBrand(): void
    {
        static::addGlobalScope(new BrandScope());

        static::creating(function (Model $model) {
            if (app()->bound('currentBrand') && app('currentBrand') && empty($model->brand_id)) {
                $model->brand_id = app('currentBrand')->getKey();
            }
        });
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}
