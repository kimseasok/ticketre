<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BrandDomain;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class BrandDomainAuditLogger
{
    public function created(BrandDomain $domain, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($domain),
        ];

        $this->persist($domain, $actor, 'brand_domain.created', $payload);
        $this->logEvent('brand_domain.created', $domain, $actor, $startedAt, $correlationId, $payload);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(BrandDomain $domain, ?User $actor, array $changes, float $startedAt, string $correlationId): void
    {
        if ($changes === []) {
            return;
        }

        $this->persist($domain, $actor, 'brand_domain.updated', $changes);
        $this->logEvent('brand_domain.updated', $domain, $actor, $startedAt, $correlationId, $changes);
    }

    public function deleted(BrandDomain $domain, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($domain),
        ];

        $this->persist($domain, $actor, 'brand_domain.deleted', $payload);
        $this->logEvent('brand_domain.deleted', $domain, $actor, $startedAt, $correlationId, $payload);
    }

    public function verificationStarted(BrandDomain $domain, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'status' => $domain->status,
        ];

        $this->persist($domain, $actor, 'brand_domain.verification_started', $payload);
        $this->logEvent('brand_domain.verification_started', $domain, $actor, $startedAt, $correlationId, $payload);
    }

    public function verificationFinished(BrandDomain $domain, ?User $actor, bool $success, float $startedAt, string $correlationId): void
    {
        $payload = [
            'status' => $domain->status,
            'dns_checked_at' => $domain->dns_checked_at?->toAtomString(),
            'ssl_checked_at' => $domain->ssl_checked_at?->toAtomString(),
            'verified_at' => $domain->verified_at?->toAtomString(),
            'verification_error' => $domain->verification_error,
            'ssl_error' => $domain->ssl_error,
        ];

        $action = $success ? 'brand_domain.verification_succeeded' : 'brand_domain.verification_failed';

        $this->persist($domain, $actor, $action, $payload);
        $this->logEvent($action, $domain, $actor, $startedAt, $correlationId, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(BrandDomain $domain): array
    {
        return [
            'domain_digest' => $domain->domainDigest(),
            'status' => $domain->status,
            'ssl_status' => $domain->ssl_status,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(BrandDomain $domain, ?User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $domain->tenant_id,
            'brand_id' => $domain->brand_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => BrandDomain::class,
            'auditable_id' => $domain->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, BrandDomain $domain, ?User $actor, float $startedAt, string $correlationId, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'brand_domain_id' => $domain->getKey(),
            'tenant_id' => $domain->tenant_id,
            'brand_id' => $domain->brand_id,
            'domain_digest' => $domain->domainDigest(),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor?->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'brand_domain_audit',
            'payload_keys' => array_keys($payload),
        ]);
    }
}
