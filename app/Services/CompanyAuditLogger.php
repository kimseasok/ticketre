<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CompanyAuditLogger
{
    public function created(Company $company, ?User $actor, float $startedAt): void
    {
        $payload = [
            'snapshot' => [
                'name' => $company->name,
                'domain_digest' => $this->hashValue($company->domain),
                'metadata_keys' => array_keys($company->metadata ?? []),
            ],
        ];

        $this->persist($company, $actor, 'company.created', $payload);
        $this->logEvent('company.created', $company, $actor, $startedAt, $payload);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $original
     */
    public function updated(Company $company, ?User $actor, array $changes, array $original, float $startedAt): void
    {
        $diff = $this->diff($company, $changes, $original);

        if (empty($diff)) {
            return;
        }

        $this->persist($company, $actor, 'company.updated', $diff);
        $this->logEvent('company.updated', $company, $actor, $startedAt, $diff);
    }

    public function deleted(Company $company, ?User $actor, float $startedAt): void
    {
        $payload = [
            'snapshot' => [
                'name' => $company->name,
                'domain_digest' => $this->hashValue($company->domain),
                'metadata_keys' => array_keys($company->metadata ?? []),
            ],
        ];

        $this->persist($company, $actor, 'company.deleted', $payload);
        $this->logEvent('company.deleted', $company, $actor, $startedAt, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(Company $company, ?User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $company->tenant_id,
            'brand_id' => $actor?->brand_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => Company::class,
            'auditable_id' => $company->getKey(),
            'changes' => $payload,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $original
     * @return array<string, mixed>
     */
    protected function diff(Company $company, array $changes, array $original): array
    {
        $diff = [];

        foreach ($changes as $field => $_value) {
            if ($field === 'metadata') {
                $diff['metadata_keys'] = [
                    'old' => array_keys((array) ($original['metadata'] ?? [])),
                    'new' => array_keys($company->metadata ?? []),
                ];

                continue;
            }

            if ($field === 'domain') {
                $diff['domain_digest'] = [
                    'old' => $this->hashValue($original['domain'] ?? null),
                    'new' => $this->hashValue($company->domain),
                ];

                continue;
            }

            $diff[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $company->{$field},
            ];
        }

        return $diff;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, Company $company, ?User $actor, float $startedAt, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;
        $correlationId = request()?->header('X-Correlation-ID');

        Log::channel(config('logging.default'))->info($action, [
            'company_id' => $company->getKey(),
            'tenant_id' => $company->tenant_id,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor?->getKey(),
            'context' => 'company_audit',
            'changes_keys' => array_keys($payload),
            'correlation_id' => $correlationId,
        ]);
    }

    protected function hashValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return hash('sha256', $value);
    }
}
