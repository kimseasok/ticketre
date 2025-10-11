<?php

namespace App\Http\Resources;

use App\Data\PortalSessionTokens;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;

class PortalSessionTokenResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var PortalSessionTokens $tokens */
        $tokens = $this->resource;
        $session = $tokens->session;
        $session->loadMissing('account');

        return [
            'type' => 'portal_sessions',
            'id' => (string) $session->getKey(),
            'attributes' => [
                'access_token' => $tokens->accessToken,
                'refresh_token' => $tokens->refreshToken,
                'expires_at' => $tokens->expiresAt->toIso8601String(),
                'refresh_expires_at' => $tokens->refreshExpiresAt->toIso8601String(),
                'expires_in' => $this->secondsUntil($tokens->expiresAt),
                'refresh_expires_in' => $this->secondsUntil($tokens->refreshExpiresAt),
                'abilities' => array_values($session->abilities ?? []),
            ],
            'relationships' => [
                'account' => [
                    'data' => [
                        'type' => 'portal_accounts',
                        'id' => (string) $session->portal_account_id,
                        'attributes' => [
                            'email' => $session->account?->email,
                            'status' => $session->account?->status,
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function secondsUntil(CarbonImmutable $moment): int
    {
        $now = CarbonImmutable::now();

        return max(0, (int) ($moment->getTimestamp() - $now->getTimestamp()));
    }
}
