<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandAsset;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BrandAsset>
 */
class BrandAssetFactory extends Factory
{
    protected $model = BrandAsset::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'brand_id' => Brand::factory(),
            'type' => fake()->randomElement(config('branding.asset_types', ['primary_logo'])),
            'disk' => config('branding.asset_disk'),
            'path' => 'brands/'.Str::uuid().'/logo.png',
            'version' => 1,
            'content_type' => 'image/png',
            'size' => fake()->numberBetween(5_000, 150_000),
            'checksum' => hash('sha256', Str::uuid()->toString()),
            'meta' => [
                'variant' => 'light',
            ],
            'cache_control' => config('branding.assets.cache_control'),
            'cdn_url' => null,
        ];
    }

    public function forBrand(Brand $brand): self
    {
        return $this->state(function () use ($brand): array {
            return [
                'tenant_id' => $brand->tenant_id,
                'brand_id' => $brand->getKey(),
            ];
        });
    }
}
