<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        $tenantId = $this->attributes['tenant_id'] ?? Tenant::factory()->create()->id;
        $tenant = Tenant::query()->findOrFail($tenantId);
        $name = $this->faker->company().' Brand';
        $slug = Str::slug($name.'-'.$tenant->id);

        return [
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => $slug,
            'domain' => $slug.'.'.$tenant->domain,
            'theme' => [
                'primary' => '#2563eb',
                'secondary' => '#0f172a',
            ],
            'primary_logo_path' => 'brands/'.$slug.'/logo-primary.png',
            'secondary_logo_path' => 'brands/'.$slug.'/logo-secondary.png',
            'favicon_path' => 'brands/'.$slug.'/favicon.ico',
            'theme_preview' => [
                'gradient' => 'linear-gradient(90deg, #2563eb 0%, #38bdf8 100%)',
                'text_color' => '#0f172a',
            ],
            'theme_settings' => [
                'button_radius' => 8,
                'font_family' => 'Inter',
            ],
        ];
    }
}
