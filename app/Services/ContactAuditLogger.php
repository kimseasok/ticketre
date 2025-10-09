<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ContactAuditLogger
{
    public function created(Contact $contact, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => [
                'name_hash' => $this->hashValue($contact->name),
                'company_id' => $contact->company_id,
                'brand_id' => $contact->brand_id,
                'tags' => array_values($contact->tags ?? []),
                'gdpr_marketing_opt_in' => $contact->gdpr_marketing_opt_in,
                'gdpr_data_processing_opt_in' => $contact->gdpr_data_processing_opt_in,
            ],
            'sensitive' => [
                'email_hash' => $this->hashValue($contact->email),
                'phone_hash' => $this->hashValue($contact->phone),
                'metadata_keys' => array_keys($contact->metadata ?? []),
            ],
        ];

        $this->persist($contact, $actor, 'contact.created', $payload, $correlationId);
        $this->logEvent('contact.created', $contact, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $original
     */
    public function updated(Contact $contact, ?User $actor, array $changes, array $original, float $startedAt, string $correlationId): void
    {
        $diff = $this->diff($contact, $changes, $original);

        if (empty($diff)) {
            return;
        }

        $this->persist($contact, $actor, 'contact.updated', $diff, $correlationId);
        $this->logEvent('contact.updated', $contact, $actor, $startedAt, $diff, $correlationId);
    }

    public function deleted(Contact $contact, ?User $actor, float $startedAt, string $correlationId): void
    {
        $payload = [
            'snapshot' => [
                'name_hash' => $this->hashValue($contact->name),
                'company_id' => $contact->company_id,
                'brand_id' => $contact->brand_id,
                'tags' => array_values($contact->tags ?? []),
                'gdpr_marketing_opt_in' => $contact->gdpr_marketing_opt_in,
                'gdpr_data_processing_opt_in' => $contact->gdpr_data_processing_opt_in,
            ],
            'sensitive' => [
                'email_hash' => $this->hashValue($contact->email),
                'phone_hash' => $this->hashValue($contact->phone),
            ],
        ];

        $this->persist($contact, $actor, 'contact.deleted', $payload, $correlationId);
        $this->logEvent('contact.deleted', $contact, $actor, $startedAt, $payload, $correlationId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(Contact $contact, ?User $actor, string $action, array $payload, string $correlationId): void
    {
        AuditLog::create([
            'tenant_id' => $contact->tenant_id,
            'brand_id' => $contact->brand_id ?? $actor?->brand_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => Contact::class,
            'auditable_id' => $contact->getKey(),
            'changes' => array_merge($payload, ['correlation_id' => $correlationId]),
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $original
     * @return array<string, mixed>
     */
    protected function diff(Contact $contact, array $changes, array $original): array
    {
        $diff = [];

        foreach ($changes as $field => $_value) {
            if ($field === 'metadata') {
                $diff['metadata_keys'] = [
                    'old' => array_keys((array) ($original['metadata'] ?? [])),
                    'new' => array_keys($contact->metadata ?? []),
                ];
                continue;
            }

            if ($field === 'tags') {
                $diff['tags'] = [
                    'old' => array_values((array) ($original['tags'] ?? [])),
                    'new' => array_values($contact->tags ?? []),
                ];
                continue;
            }

            if ($field === 'email') {
                $diff['email_hash'] = [
                    'old' => $this->hashValue($original['email'] ?? null),
                    'new' => $this->hashValue($contact->email),
                ];
                continue;
            }

            if ($field === 'phone') {
                $diff['phone_hash'] = [
                    'old' => $this->hashValue($original['phone'] ?? null),
                    'new' => $this->hashValue($contact->phone),
                ];
                continue;
            }

            if ($field === 'name') {
                $diff['name_hash'] = [
                    'old' => $this->hashValue($original['name'] ?? null),
                    'new' => $this->hashValue($contact->name),
                ];
                continue;
            }

            $diff[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $contact->{$field},
            ];
        }

        return $diff;
    }

    protected function hashValue(?string $value): string
    {
        return hash('sha256', (string) $value);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, Contact $contact, ?User $actor, float $startedAt, array $payload, string $correlationId): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'contact_id' => $contact->getKey(),
            'tenant_id' => $contact->tenant_id,
            'company_id' => $contact->company_id,
            'brand_id' => $contact->brand_id,
            'changes_keys' => array_keys($payload),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor?->getKey(),
            'context' => 'contact_audit',
            'correlation_id' => $correlationId,
        ]);
    }
}
