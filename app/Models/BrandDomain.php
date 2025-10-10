<?php

namespace App\Models;

use App\Traits\BelongsToBrand;
use App\Traits\BelongsToTenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BrandDomain extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToTenant;
    use BelongsToBrand;

    protected $fillable = [
        'tenant_id',
        'brand_id',
        'domain',
        'status',
        'verification_token',
        'dns_checked_at',
        'ssl_checked_at',
        'verified_at',
        'ssl_status',
        'dns_records',
        'verification_error',
        'ssl_error',
        'correlation_id',
    ];

    protected $casts = [
        'dns_checked_at' => 'datetime',
        'ssl_checked_at' => 'datetime',
        'verified_at' => 'datetime',
        'dns_records' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function domainDigest(): string
    {
        return hash('sha256', (string) $this->domain);
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $model = $this->newQueryWithoutScopes()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();

        if (! $model) {
            throw (new ModelNotFoundException())->setModel(static::class, [$value]);
        }

        $currentTenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $currentBrand = app()->bound('currentBrand') ? app('currentBrand') : null;

        if (! $currentTenant || $model->tenant_id !== $currentTenant->getKey()) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if ($currentBrand && $model->brand_id !== $currentBrand->getKey()) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        return $model;
    }
}
