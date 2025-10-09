<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CompanyAuditLogger
{
    public function created(Company $company, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($company),
        ];

        $this->persist($company, $actor, 'company.created', $payload, $correlationId);
        $this->logEvent('company.created', $company, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updated(Company $company, User $actor, array $changes, float $startedAt, string $correlationId): void
    {
        if (empty($changes)) {
            return;
        }

        $this->persist($company, $actor, 'company.updated', $changes, $correlationId);
        $this->logEvent('company.updated', $company, $actor, $startedAt, $changes, $correlationId);
    }

    public function deleted(Company $company, User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => $this->snapshot($company),
        ];

        $this->persist($company, $actor, 'company.deleted', $payload, $correlationId);
        $this->logEvent('company.deleted', $company, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(Company $company, User $actor, string $action, array $payload, string $correlationId): void
    {
        AuditLog::create([
            'tenant_id' => $company->tenant_id,
            'brand_id' => $company->brand_id,
            'user_id' => $actor->getKey(),
            'action' => $action,
            'auditable_type' => Company::class,
            'auditable_id' => $company->getKey(),
            'changes' => array_merge($payload, ['correlation_id' => $correlationId]),
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(Company $company): array
    {
        return [
            'name_hash' => $this->hashValue($company->name),
            'domain_hash' => $this->hashValue($company->domain),
            'brand_id' => $company->brand_id,
            'tags' => array_values($company->tags ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, Company $company, User $actor, float $startedAt, array $payload, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'company_id' => $company->getKey(),
            'tenant_id' => $company->tenant_id,
            'brand_id' => $company->brand_id,
            'changes_keys' => array_keys($payload),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'company_audit',
        ]);
    }

    protected function hashValue(?string $value): string
    {
        return hash('sha256', (string) $value);
    }
}
