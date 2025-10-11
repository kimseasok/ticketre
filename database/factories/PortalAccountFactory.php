<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Contact;
use App\Models\PortalAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PortalAccountFactory extends Factory
{
    protected $model = PortalAccount::class;

    public function definition(): array
    {
        $existingContact = isset($this->attributes['contact_id'])
            ? Contact::query()->find($this->attributes['contact_id'])
            : null;

        $tenantId = $this->attributes['tenant_id']
            ?? $existingContact?->tenant_id
            ?? Tenant::factory()->create()->id;

        $brandId = $this->attributes['brand_id']
            ?? $existingContact?->brand_id
            ?? Brand::factory()->create(['tenant_id' => $tenantId])->id;

        $contact = $existingContact ?: Contact::factory()->create([
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
        ]);

        $email = $this->faker->unique()->safeEmail();

        return [
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
            'contact_id' => $contact->id,
            'email' => $email,
            'password' => Hash::make('PortalPass123!'),
            'status' => PortalAccount::STATUS_ACTIVE,
            'metadata' => [
                'reference' => Str::uuid()->toString(),
            ],
            'last_login_at' => null,
        ];
    }
}
