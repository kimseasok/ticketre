<?php

namespace Database\Factories;

use App\Models\TwoFactorCredential;
use App\Models\TwoFactorRecoveryCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TwoFactorRecoveryCodeFactory extends Factory
{
    protected $model = TwoFactorRecoveryCode::class;

    public function definition(): array
    {
        return [
            'two_factor_credential_id' => TwoFactorCredential::factory(),
            'code_hash' => Hash::make(Str::random(16)),
            'used_at' => null,
        ];
    }

    public function used(): self
    {
        return $this->state(fn () => [
            'used_at' => now(),
        ]);
    }
}
