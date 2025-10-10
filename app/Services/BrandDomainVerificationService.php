<?php

namespace App\Services;

use App\Models\BrandDomain;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BrandDomainVerificationService
{
    public function __construct(
        private readonly BrandDomainProbe $probe,
        private readonly BrandDomainAuditLogger $auditLogger,
    ) {
    }

    public function verify(BrandDomain $domain, ?User $actor, string $correlationId): BrandDomain
    {
        $startedAt = microtime(true);

        $dns = $this->probe->checkDns($domain->domain);
        $ssl = $this->probe->checkSsl($domain->domain);

        $status = ($dns['verified'] ?? false) && ($ssl['verified'] ?? false) ? 'verified' : 'failed';
        $now = now();

        DB::transaction(function () use ($domain, $dns, $ssl, $status, $now, $correlationId): void {
            $domain->forceFill([
                'status' => $status,
                'dns_checked_at' => $now,
                'ssl_checked_at' => $now,
                'verified_at' => $status === 'verified' ? $now : null,
                'dns_records' => $dns['records'] ?? [],
                'verification_error' => $status === 'verified' ? null : ($dns['error'] ?? null),
                'ssl_error' => $status === 'verified' ? null : ($ssl['error'] ?? null),
                'ssl_status' => $ssl['verified'] ? 'active' : 'unverified',
                'correlation_id' => $correlationId,
            ])->save();
        });

        $domain->refresh();

        $this->auditLogger->verificationFinished($domain, $actor, $status === 'verified', $startedAt, $correlationId);
        $this->logPerformance('brand_domain.verify.finish', $domain, $actor, $startedAt, $correlationId);

        return $domain;
    }

    protected function logPerformance(string $action, BrandDomain $domain, ?User $actor, float $startedAt, string $correlationId): void
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
            'context' => 'brand_domain_verification',
            'status' => $domain->status,
            'ssl_status' => $domain->ssl_status,
        ]);
    }
}
