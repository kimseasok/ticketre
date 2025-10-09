<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\TwoFactorCredential;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TwoFactorAuditLogger
{
    public function enrollmentStarted(TwoFactorCredential $credential, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'user_id' => $credential->user_id,
            'secret_hash' => $this->hashSecret($credential->secret),
        ];

        $this->persist($credential, $actor, 'two_factor.enrollment_started', $payload, $correlationId);
        $this->logEvent('two_factor.enrollment_started', $credential, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @param  array<int, string>  $recoveryCodes
     */
    public function enrollmentConfirmed(TwoFactorCredential $credential, User $actor, array $recoveryCodes, float $startedAt, string $correlationId): void
    {
        $payload = [
            'user_id' => $credential->user_id,
            'codes_issued' => count($recoveryCodes),
        ];

        $this->persist($credential, $actor, 'two_factor.enrollment_confirmed', $payload, $correlationId);
        $this->logEvent('two_factor.enrollment_confirmed', $credential, $actor, $startedAt, $payload, $correlationId);
    }

    public function challengeVerified(TwoFactorCredential $credential, User $actor, float $startedAt, string $correlationId, string $method): void
    {
        $payload = [
            'user_id' => $credential->user_id,
            'method' => $method,
        ];

        $this->persist($credential, $actor, 'two_factor.challenge_verified', $payload, $correlationId);
        $this->logEvent('two_factor.challenge_verified', $credential, $actor, $startedAt, $payload, $correlationId);
    }

    public function recoveryCodesRegenerated(TwoFactorCredential $credential, User $actor, float $startedAt, string $correlationId, int $issued): void
    {
        $payload = [
            'user_id' => $credential->user_id,
            'codes_issued' => $issued,
        ];

        $this->persist($credential, $actor, 'two_factor.recovery_regenerated', $payload, $correlationId);
        $this->logEvent('two_factor.recovery_regenerated', $credential, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(TwoFactorCredential $credential, User $actor, string $action, array $payload, string $correlationId): void
    {
        AuditLog::create([
            'tenant_id' => $credential->tenant_id,
            'brand_id' => $credential->brand_id ?? $actor->brand_id,
            'user_id' => $actor->getKey(),
            'action' => $action,
            'auditable_type' => TwoFactorCredential::class,
            'auditable_id' => $credential->getKey(),
            'changes' => array_merge($payload, ['correlation_id' => $correlationId]),
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, TwoFactorCredential $credential, User $actor, float $startedAt, array $payload, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'two_factor_id' => $credential->getKey(),
            'tenant_id' => $credential->tenant_id,
            'brand_id' => $credential->brand_id,
            'user_id' => $actor->getKey(),
            'method' => $payload['method'] ?? null,
            'codes_issued' => $payload['codes_issued'] ?? null,
            'duration_ms' => round($durationMs, 2),
            'correlation_id' => $correlationId,
            'context' => 'two_factor',
        ]);
    }

    protected function hashSecret(string $value): string
    {
        return hash('sha256', $value);
    }
}
