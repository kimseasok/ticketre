<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BrandConfigurationService
{
    public function __construct(private readonly BrandConfigurationAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?string $correlationId = null): Brand
    {
        $startedAt = microtime(true);
        if (! array_key_exists('tenant_id', $data)) {
            $data['tenant_id'] = $actor->tenant_id;
        }

        $attributes = $this->prepareAttributes($data);
        $correlation = $this->resolveCorrelationId($correlationId);

        /** @var Brand $brand */
        $brand = DB::transaction(fn () => Brand::create($attributes));
        $brand->refresh();

        $this->auditLogger->created($brand, $actor, $startedAt, $correlation);
        $this->logPerformance('brand.create', $brand, $actor, $startedAt, $correlation);

        return $brand;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Brand $brand, array $data, User $actor, ?string $correlationId = null): Brand
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data, $brand);
        $correlation = $this->resolveCorrelationId($correlationId);

        $original = Arr::only($brand->getOriginal(), [
            'name',
            'slug',
            'domain',
            'theme',
            'primary_logo_path',
            'secondary_logo_path',
            'favicon_path',
            'theme_preview',
            'theme_settings',
        ]);

        $dirty = [];

        DB::transaction(function () use ($brand, $attributes, &$dirty): void {
            $brand->fill($attributes);
            $dirty = Arr::except($brand->getDirty(), ['updated_at']);
            $brand->save();
        });

        $brand->refresh();

        $changes = $this->formatChanges($brand, $original, $dirty);

        $this->auditLogger->updated($brand, $actor, $changes, $startedAt, $correlation);
        $this->logPerformance('brand.update', $brand, $actor, $startedAt, $correlation);

        return $brand;
    }

    public function delete(Brand $brand, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        DB::transaction(fn () => $brand->delete());

        $this->auditLogger->deleted($brand, $actor, $startedAt, $correlation);
        $this->logPerformance('brand.delete', $brand, $actor, $startedAt, $correlation);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?Brand $brand = null): array
    {
        $attributes = Arr::only($data, [
            'name',
            'slug',
            'domain',
            'theme',
            'primary_logo_path',
            'secondary_logo_path',
            'favicon_path',
            'theme_settings',
        ]);

        if (! array_key_exists('tenant_id', $attributes) || $attributes['tenant_id'] === null) {
            $attributes['tenant_id'] = $brand?->tenant_id
                ?? (app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null);
        }

        $attributes['name'] = $attributes['name'] ?? $brand?->name ?? 'Brand '.Str::random(6);

        if (empty($attributes['slug'])) {
            $source = $attributes['name'] ?? $brand?->name ?? 'brand';
            $attributes['slug'] = Str::slug($source.'-'.Str::random(6));
        }

        if (isset($attributes['domain'])) {
            $attributes['domain'] = strtolower(trim((string) $attributes['domain']));
        }

        $theme = $this->normalizeTheme($attributes['theme'] ?? $brand?->theme ?? []);
        $attributes['theme'] = $theme;
        $attributes['theme_preview'] = $this->generateThemePreview($theme);

        if (isset($attributes['theme_settings'])) {
            $attributes['theme_settings'] = $this->normalizeThemeSettings($attributes['theme_settings']);
        } elseif ($brand) {
            $attributes['theme_settings'] = $brand->theme_settings ?? [];
        } else {
            $attributes['theme_settings'] = [
                'button_radius' => 6,
                'font_family' => 'Inter',
            ];
        }

        foreach (['primary_logo_path', 'secondary_logo_path', 'favicon_path'] as $assetKey) {
            if (isset($attributes[$assetKey])) {
                $attributes[$assetKey] = $this->sanitizePath($attributes[$assetKey]);
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $theme
     * @return array<string, string>
     */
    protected function generateThemePreview(array $theme): array
    {
        $primary = $theme['primary'] ?? '#2563eb';
        $secondary = $theme['secondary'] ?? '#0f172a';

        return [
            'gradient' => sprintf('linear-gradient(90deg, %s 0%%, %s 100%%)', $primary, $secondary),
            'text_color' => $theme['text'] ?? '#0f172a',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $theme
     * @return array<string, string>
     */
    protected function normalizeTheme($theme): array
    {
        $theme = (array) $theme;

        $colors = [
            'primary' => $this->sanitizeColor($theme['primary'] ?? '#2563eb'),
            'secondary' => $this->sanitizeColor($theme['secondary'] ?? '#0f172a'),
            'accent' => $this->sanitizeColor($theme['accent'] ?? '#38bdf8'),
            'text' => $this->sanitizeColor($theme['text'] ?? '#0f172a'),
        ];

        return $colors;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function normalizeThemeSettings(array $settings): array
    {
        return [
            'button_radius' => (int) ($settings['button_radius'] ?? 6),
            'font_family' => is_string($settings['font_family'] ?? null) ? Str::limit($settings['font_family'], 64, '') : 'Inter',
        ];
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

    protected function sanitizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim($path);

        if ($path === '') {
            return null;
        }

        return Str::of($path)->replace('..', '')->ltrim('/')->toString();
    }

    /**
     * @param  array<string, mixed>  $dirty
     * @return array<string, mixed>
     */
    protected function formatChanges(Brand $brand, array $original, array $dirty): array
    {
        $changes = [];

        foreach ($dirty as $field => $_value) {
            if ($field === 'domain') {
                $changes['domain_digest'] = [
                    'old' => isset($original['domain']) ? hash('sha256', (string) $original['domain']) : null,
                    'new' => $brand->domain ? hash('sha256', (string) $brand->domain) : null,
                ];

                continue;
            }

            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $brand->{$field},
            ];
        }

        return $changes;
    }

    protected function resolveCorrelationId(?string $correlationId): string
    {
        if ($correlationId && Str::length($correlationId) <= 64) {
            return $correlationId;
        }

        return (string) Str::uuid();
    }

    protected function logPerformance(string $action, Brand $brand, User $actor, float $startedAt, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'brand_id' => $brand->getKey(),
            'tenant_id' => $brand->tenant_id,
            'domain_digest' => $brand->domain ? hash('sha256', (string) $brand->domain) : null,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'brand_configuration',
        ]);
    }
}
