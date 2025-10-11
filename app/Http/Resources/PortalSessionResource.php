<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PortalSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        $session = $this->resource;

        return [
            'type' => 'portal_sessions',
            'id' => (string) $session->getKey(),
            'attributes' => [
                'status' => $session->status,
                'tenant_id' => $session->tenant_id,
                'brand_id' => $session->brand_id,
                'portal_account_id' => $session->portal_account_id,
                'abilities' => array_values($session->abilities ?? []),
                'issued_at' => optional($session->issued_at)->toIso8601String(),
                'expires_at' => optional($session->expires_at)->toIso8601String(),
                'refresh_expires_at' => optional($session->refresh_expires_at)->toIso8601String(),
                'last_used_at' => optional($session->last_used_at)->toIso8601String(),
                'revoked_at' => optional($session->revoked_at)->toIso8601String(),
                'correlation_id' => $session->correlation_id,
                'ip_hash' => $session->ip_hash,
                'user_agent' => $session->user_agent,
                'metadata' => $session->metadata ?? [],
            ],
            'relationships' => [
                'account' => $this->when($session->relationLoaded('account'), [
                    'data' => [
                        'type' => 'portal_accounts',
                        'id' => (string) $session->account->getKey(),
                        'attributes' => [
                            'email' => $session->account->email,
                            'status' => $session->account->status,
                        ],
                    ],
                ]),
            ],
        ];
    }
}
