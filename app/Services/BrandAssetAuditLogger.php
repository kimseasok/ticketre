<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BrandAsset;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class BrandAssetAuditLogger
{
    public function created(BrandAsset $asset, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($asset),
        ];

        $this->persist($asset, $actor, 'brand_asset.created', $payload);
        $this->logEvent('brand_asset.created', $asset, $actor, $startedAt, $correlationId, $payload);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(BrandAsset $asset, User $actor, array $changes, float $startedAt, string $correlationId): void
    {
        if ($changes === []) {
            return;
        }

        $this->persist($asset, $actor, 'brand_asset.updated', $changes);
        $this->logEvent('brand_asset.updated', $asset, $actor, $startedAt, $correlationId, $changes);
    }

    public function deleted(BrandAsset $asset, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($asset),
        ];

        $this->persist($asset, $actor, 'brand_asset.deleted', $payload);
        $this->logEvent('brand_asset.deleted', $asset, $actor, $startedAt, $correlationId, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(BrandAsset $asset): array
    {
        return [
            'type' => $asset->type,
            'version' => $asset->version,
            'path_digest' => $asset->pathDigest(),
            'content_type' => $asset->content_type,
            'size' => $asset->size,
            'cache_control' => $asset->cache_control,
            'cdn_url_digest' => $asset->cdn_url ? hash('sha256', $asset->cdn_url) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(BrandAsset $asset, User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $asset->tenant_id,
            'brand_id' => $asset->brand_id,
            'user_id' => $actor->getKey(),
            'action' => $action,
            'auditable_type' => BrandAsset::class,
            'auditable_id' => $asset->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, BrandAsset $asset, User $actor, float $startedAt, string $correlationId, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'brand_asset_id' => $asset->getKey(),
            'tenant_id' => $asset->tenant_id,
            'brand_id' => $asset->brand_id,
            'type' => $asset->type,
            'version' => $asset->version,
            'size' => $asset->size,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'brand_asset_audit',
            'payload_keys' => array_keys($payload),
        ]);
    }
}
