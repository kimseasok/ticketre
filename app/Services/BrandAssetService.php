<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\BrandAsset;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandAssetService
{
    public function __construct(private readonly BrandAssetAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?string $correlationId = null): BrandAsset
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);
        $attributes = $this->prepareAttributes($data);

        /** @var BrandAsset $asset */
        $asset = DB::transaction(function () use ($attributes): BrandAsset {
            $attributes['version'] = $this->nextVersion((int) $attributes['brand_id'], (string) $attributes['type']);

            return BrandAsset::create($attributes);
        });

        $asset->refresh();

        $this->auditLogger->created($asset, $actor, $startedAt, $correlation);
        $this->logPerformance('brand_asset.create', $asset, $actor, $startedAt, $correlation);

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BrandAsset $asset, array $data, User $actor, ?string $correlationId = null): BrandAsset
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);
        $original = Arr::only($asset->getOriginal(), [
            'type',
            'disk',
            'path',
            'version',
            'content_type',
            'size',
            'checksum',
            'cache_control',
            'cdn_url',
        ]);

        $attributes = $this->prepareAttributes($data, $asset);

        DB::transaction(function () use ($asset, $attributes): void {
            if ($this->shouldBumpVersion($asset, $attributes)) {
                $attributes['version'] = $this->nextVersion($asset->brand_id, $attributes['type'] ?? $asset->type);
            }

            $asset->fill($attributes);
            $asset->save();
        });

        $asset->refresh();

        $changes = $this->formatChanges($asset, $original);

        $this->auditLogger->updated($asset, $actor, $changes, $startedAt, $correlation);
        $this->logPerformance('brand_asset.update', $asset, $actor, $startedAt, $correlation);

        return $asset;
    }

    public function delete(BrandAsset $asset, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        DB::transaction(fn () => $asset->delete());

        $this->auditLogger->deleted($asset, $actor, $startedAt, $correlation);
        $this->logPerformance('brand_asset.delete', $asset, $actor, $startedAt, $correlation);
    }

    public function deliver(BrandAsset $asset, User $actor, ?string $correlationId = null): array
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        $cacheControl = $asset->cache_control ?? config('branding.assets.cache_control');
        $url = $this->resolveAssetUrl($asset);
        $etag = $asset->etag();

        $this->logPerformance('brand_asset.deliver', $asset, $actor, $startedAt, $correlation, [
            'url_digest' => $url ? hash('sha256', $url) : null,
        ]);

        return [
            'url' => $url,
            'cdn_url' => $asset->cdn_url,
            'cache_control' => $cacheControl,
            'etag' => $etag,
            'version' => $asset->version,
            'content_type' => $asset->content_type,
        ];
    }

    public function themeConfiguration(Brand $brand, ?User $actor = null, ?string $correlationId = null): array
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        $defaults = config('branding.defaults');
        $colors = array_merge($defaults['theme']['colors'], (array) ($brand->theme ?? []));
        $settings = array_merge($defaults['theme']['settings'], (array) ($brand->theme_settings ?? []));

        $assets = BrandAsset::query()
            ->where('brand_id', $brand->getKey())
            ->orderByDesc('version')
            ->get()
            ->groupBy('type')
            ->map(fn ($collection) => $collection->first());

        $assetUrls = [];
        foreach (config('branding.asset_types', []) as $type) {
            /** @var BrandAsset|null $asset */
            $asset = $assets->get($type);
            $assetUrls[$type] = $asset ? $this->resolveAssetUrl($asset, true) : $defaults['assets'][$type] ?? null;
            if ($asset && $assetUrls[$type]) {
                $assetUrls[$type] .= '?v='.$asset->version;
            }
        }

        $versionSeed = implode('|', [
            $brand->getKey(),
            $brand->updated_at?->timestamp ?? 0,
            $assets->map(fn (BrandAsset $asset) => $asset->version.'-'.$asset->etag())->implode('|'),
        ]);

        $version = Str::substr(hash('sha256', $versionSeed), 0, 32);

        $configuration = [
            'version' => $version,
            'cache_control' => config('branding.assets.cache_control'),
            'colors' => [
                'primary' => $this->sanitizeColor($colors['primary'] ?? $defaults['theme']['colors']['primary']),
                'secondary' => $this->sanitizeColor($colors['secondary'] ?? $defaults['theme']['colors']['secondary']),
                'accent' => $this->sanitizeColor($colors['accent'] ?? $defaults['theme']['colors']['accent']),
                'text' => $this->sanitizeColor($colors['text'] ?? $defaults['theme']['colors']['text']),
            ],
            'typography' => [
                'font_family' => Str::limit((string) ($settings['font_family'] ?? $defaults['theme']['settings']['font_family']), 64, ''),
            ],
            'components' => [
                'button_radius' => (int) ($settings['button_radius'] ?? $defaults['theme']['settings']['button_radius']),
            ],
            'assets' => $assetUrls,
        ];

        $this->logThemeDelivery($brand, $actor, $startedAt, $correlation, $version);

        return $configuration;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function shouldBumpVersion(BrandAsset $asset, array $attributes): bool
    {
        $fields = ['path', 'checksum', 'content_type', 'cdn_url'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $attributes) && $attributes[$field] !== $asset->{$field}) {
                return true;
            }
        }

        if (array_key_exists('type', $attributes) && $attributes['type'] !== $asset->type) {
            return true;
        }

        return false;
    }

    protected function nextVersion(int $brandId, string $type): int
    {
        $current = BrandAsset::withTrashed()
            ->where('brand_id', $brandId)
            ->where('type', $type)
            ->max('version');

        return ((int) $current) + 1;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?BrandAsset $asset = null): array
    {
        $attributes = array_filter([
            'tenant_id' => $data['tenant_id'] ?? $asset?->tenant_id,
            'brand_id' => $data['brand_id'] ?? $asset?->brand_id,
            'type' => isset($data['type']) ? $this->normalizeType((string) $data['type']) : $asset?->type,
            'disk' => $data['disk'] ?? $asset?->disk ?? config('branding.asset_disk'),
            'content_type' => $data['content_type'] ?? $asset?->content_type,
            'size' => $data['size'] ?? $asset?->size,
            'checksum' => $data['checksum'] ?? $asset?->checksum,
            'cache_control' => $data['cache_control'] ?? $asset?->cache_control ?? config('branding.assets.cache_control'),
            'cdn_url' => $data['cdn_url'] ?? $asset?->cdn_url,
        ], fn ($value) => $value !== null);

        if (isset($data['path'])) {
            $attributes['path'] = $this->sanitizePath((string) $data['path']);
        }

        if (isset($data['meta'])) {
            $attributes['meta'] = $this->sanitizeMeta($data['meta']);
        } elseif ($asset?->meta !== null && ! array_key_exists('meta', $attributes)) {
            $attributes['meta'] = $asset->meta;
        }

        if (! isset($attributes['tenant_id']) && app()->bound('currentTenant') && app('currentTenant')) {
            $attributes['tenant_id'] = app('currentTenant')->getKey();
        }

        return $attributes;
    }

    protected function sanitizePath(string $path): string
    {
        return Str::of(trim($path))
            ->replace('..', '')
            ->ltrim('/')
            ->limit(2048, '')
            ->toString();
    }

    /**
     * @param  mixed  $meta
     * @return array<string, mixed>
     */
    protected function sanitizeMeta($meta): array
    {
        $meta = (array) $meta;

        return collect($meta)
            ->map(fn ($value) => is_array($value) ? $value : (is_scalar($value) ? $value : null))
            ->filter(fn ($value) => $value !== null)
            ->toArray();
    }

    protected function normalizeType(string $type): string
    {
        $allowed = config('branding.asset_types', []);
        $normalized = Str::of($type)->lower()->snake()->toString();

        if ($allowed !== [] && ! in_array($normalized, $allowed, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported brand asset type [%s].', $type));
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $original
     * @return array<string, mixed>
     */
    protected function formatChanges(BrandAsset $asset, array $original): array
    {
        $changes = [];

        foreach ($original as $field => $value) {
            $current = $asset->{$field};

            if ($current === $value) {
                continue;
            }

            if ($field === 'path') {
                $changes['path_digest'] = [
                    'old' => $value ? hash('sha256', (string) $value) : null,
                    'new' => $asset->pathDigest(),
                ];

                continue;
            }

            if ($field === 'cdn_url') {
                $changes['cdn_url_digest'] = [
                    'old' => $value ? hash('sha256', (string) $value) : null,
                    'new' => $current ? hash('sha256', (string) $current) : null,
                ];

                continue;
            }

            $changes[$field] = [
                'old' => $value,
                'new' => $current,
            ];
        }

        return $changes;
    }

    protected function resolveAssetUrl(BrandAsset $asset, bool $suppressMissing = false): ?string
    {
        if ($asset->cdn_url) {
            return $asset->cdn_url;
        }

        try {
            return Storage::disk($asset->disk)->url($asset->path);
        } catch (\Throwable $exception) {
            if ($suppressMissing) {
                return null;
            }

            throw $exception;
        }
    }

    protected function sanitizeColor(?string $color): string
    {
        $color = trim((string) $color);

        if ($color === '') {
            return '#000000';
        }

        if (! Str::startsWith($color, '#')) {
            $color = '#'.$color;
        }

        if (strlen($color) === 4) {
            $color = sprintf('#%s%s%s%s%s%s', $color[1], $color[1], $color[2], $color[2], $color[3], $color[3]);
        }

        return strtolower(substr($color, 0, 7));
    }

    protected function resolveCorrelationId(?string $correlationId): string
    {
        if ($correlationId && Str::length($correlationId) <= 64) {
            return $correlationId;
        }

        return (string) Str::uuid();
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    protected function logPerformance(string $action, BrandAsset $asset, User $actor, float $startedAt, string $correlationId, ?array $context = null): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, array_merge([
            'brand_asset_id' => $asset->getKey(),
            'tenant_id' => $asset->tenant_id,
            'brand_id' => $asset->brand_id,
            'type' => $asset->type,
            'version' => $asset->version,
            'path_digest' => $asset->pathDigest(),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'brand_asset_service',
        ], $context ?? []));
    }

    protected function logThemeDelivery(Brand $brand, ?User $actor, float $startedAt, string $correlationId, string $version): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info('brand_theme.deliver', [
            'brand_id' => $brand->getKey(),
            'tenant_id' => $brand->tenant_id,
            'version' => $version,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor?->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'brand_asset_service',
        ]);
    }
}
