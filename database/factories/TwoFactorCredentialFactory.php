<?php

namespace Database\Factories;

use App\Models\TwoFactorCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

class TwoFactorCredentialFactory extends Factory
{
    protected $model = TwoFactorCredential::class;

    public function definition(): array
    {
        $user = $this->attributes['user_id'] ?? User::factory()->create();

        if ($user instanceof User === false) {
            $user = User::findOrFail($user);
        }

        $secret = str()->random(32);

        return [
            'tenant_id' => $user->tenant_id,
            'brand_id' => $user->brand_id,
            'user_id' => $user->getKey(),
            'label' => $this->faker->userName(),
            'secret' => Crypt::encryptString($secret),
            'confirmed_at' => null,
            'last_verified_at' => null,
            'failed_attempts' => 0,
            'locked_until' => null,
            'metadata' => [],
        ];
    }

    public function confirmed(): self
    {
        return $this->state(function () {
            return [
                'confirmed_at' => now(),
            ];
        });
    }

    public function locked(): self
    {
        return $this->state(function () {
            return [
                'locked_until' => now()->addMinutes(10),
            ];
        });
    }
}
