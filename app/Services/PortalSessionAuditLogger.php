<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PortalSession;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PortalSessionAuditLogger
{
    public function issued(PortalSession $session, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'portal_account_id' => $session->portal_account_id,
            'abilities' => $session->abilities,
            'access_token_id' => $session->access_token_id,
            'ip_hash' => $session->ip_hash,
            'device_name' => $session->metadata['device_name'] ?? null,
        ];

        $this->persist($session, $actor, 'portal_session.issued', $payload);
        $this->logEvent('portal.session.issued', $session, $actor, $startedAt, $correlationId, $payload);
    }

    public function refreshed(PortalSession $session, ?User $actor, string $previousAccessTokenId, float $startedAt, string $correlationId): void
    {
        $payload = [
            'portal_account_id' => $session->portal_account_id,
            'abilities' => $session->abilities,
            'access_token_id' => $session->access_token_id,
            'previous_access_token_id' => $previousAccessTokenId,
            'ip_hash' => $session->ip_hash,
            'device_name' => $session->metadata['device_name'] ?? null,
        ];

        $this->persist($session, $actor, 'portal_session.refreshed', $payload);
        $this->logEvent('portal.session.refreshed', $session, $actor, $startedAt, $correlationId, $payload);
    }

    public function revoked(PortalSession $session, ?User $actor, string $reason, float $startedAt, string $correlationId): void
    {
        $payload = [
            'portal_account_id' => $session->portal_account_id,
            'abilities' => $session->abilities,
            'access_token_id' => $session->access_token_id,
            'reason' => $reason,
            'ip_hash' => $session->ip_hash,
            'device_name' => $session->metadata['device_name'] ?? null,
        ];

        $this->persist($session, $actor, 'portal_session.revoked', $payload);
        $this->logEvent('portal.session.revoked', $session, $actor, $startedAt, $correlationId, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(PortalSession $session, ?User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $session->tenant_id,
            'brand_id' => $session->brand_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => PortalSession::class,
            'auditable_id' => $session->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, PortalSession $session, ?User $actor, float $startedAt, string $correlationId, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'session_id' => $session->getKey(),
            'portal_account_id' => $session->portal_account_id,
            'tenant_id' => $session->tenant_id,
            'brand_id' => $session->brand_id,
            'correlation_id' => $correlationId,
            'duration_ms' => round($durationMs, 2),
            'context' => 'portal_session',
            'actor_user_id' => $actor?->getKey(),
            'portal_account_hash' => $session->account ? hash('sha256', strtolower((string) $session->account->email)) : null,
            'payload_keys' => array_keys($payload),
        ]);
    }
}
