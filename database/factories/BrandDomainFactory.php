<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandDomain;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BrandDomainFactory extends Factory
{
    protected $model = BrandDomain::class;

    public function definition(): array
    {
        $tenantId = $this->attributes['tenant_id'] ?? Tenant::factory()->create()->id;
        $brandId = $this->attributes['brand_id'] ?? Brand::factory()->create(['tenant_id' => $tenantId])->id;
        $domain = Str::slug($this->faker->domainWord().'-'.$brandId).'.'.$this->faker->randomElement(['ticketre.test', 'ticketre.local']);

        return [
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
            'domain' => $domain,
            'status' => 'pending',
            'verification_token' => Str::random(32),
            'dns_records' => [],
        ];
    }

    public function verified(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'verified',
                'verified_at' => now(),
                'dns_checked_at' => now()->subMinutes(5),
                'ssl_checked_at' => now()->subMinutes(5),
                'ssl_status' => 'active',
            ];
        });
    }
}
