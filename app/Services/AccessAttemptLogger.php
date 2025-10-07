<?php

namespace App\Services;

use App\Models\AccessAttempt;
use App\Models\Brand;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AccessAttemptLogger
{
    public function log(Request $request, ?User $user, string $permission, string $reason, bool $granted, array $metadata = []): void
    {
        $tenant = $this->resolveTenant($user);
        $brand = $this->resolveBrand($user);
        $correlationId = $this->resolveCorrelationId($request);

        $payload = [
            'tenant_id' => $tenant?->getKey(),
            'brand_id' => $brand?->getKey(),
            'user_id' => $user?->getKey(),
            'route' => $request->route()?->getName() ?: $request->path(),
            'permission' => $permission,
            'granted' => $granted,
            'reason' => $reason,
            'correlation_id' => $correlationId,
            'ip_hash' => $this->hashValue($request->ip()),
            'user_agent_hash' => $this->hashValue($request->userAgent()),
            'metadata' => $this->sanitizeMetadata($request, $metadata),
        ];

        AccessAttempt::create($payload);

        Log::channel('stack')->notice('authorization_attempt', [
            'tenant_id' => $payload['tenant_id'],
            'brand_id' => $payload['brand_id'],
            'user_id' => $payload['user_id'],
            'permission' => $permission,
            'route' => $payload['route'],
            'granted' => $granted,
            'reason' => $reason,
            'correlation_id' => $correlationId,
            'metadata' => $payload['metadata'],
        ]);
    }

    protected function resolveTenant(?User $user): ?Tenant
    {
        if ($user?->relationLoaded('tenant')) {
            return $user->tenant;
        }

        if ($user?->tenant_id) {
            return Tenant::query()->find($user->tenant_id);
        }

        if (app()->bound('currentTenant') && app('currentTenant') instanceof Tenant) {
            return app('currentTenant');
        }

        return null;
    }

    protected function resolveBrand(?User $user): ?Brand
    {
        if ($user?->relationLoaded('brand')) {
            return $user->brand;
        }

        if ($user?->brand_id) {
            return Brand::query()->find($user->brand_id);
        }

        if (app()->bound('currentBrand') && app('currentBrand') instanceof Brand) {
            return app('currentBrand');
        }

        return null;
    }

    protected function resolveCorrelationId(Request $request): string
    {
        return $request->headers->get('X-Correlation-ID') ?: Str::uuid()->toString();
    }

    protected function hashValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return hash('sha256', $value);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    protected function sanitizeMetadata(Request $request, array $metadata): array
    {
        $defaults = [
            'method' => $request->getMethod(),
            'path' => '/'.ltrim($request->path(), '/'),
        ];

        return array_filter(Arr::only($metadata + $defaults, ['method', 'path', 'context']));
    }
}
