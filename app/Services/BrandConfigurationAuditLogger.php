<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class BrandConfigurationAuditLogger
{
    public function created(Brand $brand, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($brand),
        ];

        $this->persist($brand, $actor, 'brand.created', $payload);
        $this->logEvent('brand.created', $brand, $actor, $startedAt, $correlationId, $payload);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(Brand $brand, ?User $actor, array $changes, float $startedAt, string $correlationId): void
    {
        if ($changes === []) {
            return;
        }

        $this->persist($brand, $actor, 'brand.updated', $changes);
        $this->logEvent('brand.updated', $brand, $actor, $startedAt, $correlationId, $changes);
    }

    public function deleted(Brand $brand, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($brand),
        ];

        $this->persist($brand, $actor, 'brand.deleted', $payload);
        $this->logEvent('brand.deleted', $brand, $actor, $startedAt, $correlationId, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(Brand $brand): array
    {
        return [
            'name_digest' => hash('sha256', (string) $brand->name),
            'domain_digest' => $brand->domain ? hash('sha256', (string) $brand->domain) : null,
            'theme' => Arr::only((array) $brand->theme, ['primary', 'secondary', 'accent', 'text']),
            'theme_preview' => $brand->theme_preview,
            'theme_settings' => $brand->theme_settings,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(Brand $brand, ?User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $brand->tenant_id,
            'brand_id' => $brand->getKey(),
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => Brand::class,
            'auditable_id' => $brand->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, Brand $brand, ?User $actor, float $startedAt, string $correlationId, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'brand_id' => $brand->getKey(),
            'tenant_id' => $brand->tenant_id,
            'domain_digest' => $brand->domain ? hash('sha256', (string) $brand->domain) : null,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor?->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'brand_configuration_audit',
            'payload_keys' => array_keys($payload),
        ]);
    }
}
