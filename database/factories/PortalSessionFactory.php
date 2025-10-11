<?php

namespace Database\Factories;

use App\Models\PortalAccount;
use App\Models\PortalSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class PortalSessionFactory extends Factory
{
    protected $model = PortalSession::class;

    public function definition(): array
    {
        $account = PortalAccount::factory()->create();
        $issuedAt = Carbon::now();

        return [
            'tenant_id' => $account->tenant_id,
            'brand_id' => $account->brand_id,
            'portal_account_id' => $account->id,
            'access_token_id' => Str::uuid()->toString(),
            'refresh_token_hash' => hash('sha256', Str::random(64)),
            'abilities' => ['portal.access', 'portal.tickets.view'],
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent' => 'FactoryClient/1.0',
            'issued_at' => $issuedAt,
            'expires_at' => (clone $issuedAt)->addMinutes(15),
            'refresh_expires_at' => (clone $issuedAt)->addWeeks(2),
            'last_used_at' => $issuedAt,
            'revoked_at' => null,
            'correlation_id' => Str::uuid()->toString(),
            'metadata' => [
                'device_name' => 'Factory Device',
            ],
        ];
    }
}
