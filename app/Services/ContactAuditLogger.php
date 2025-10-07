<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ContactAuditLogger
{
    public function created(Contact $contact, ?User $actor, float $startedAt): void
    {
        $payload = $this->snapshotPayload($contact);

        $this->persist($contact, $actor, 'contact.created', $payload);
        $this->logEvent('contact.created', $contact, $actor, $startedAt, $payload);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $original
     */
    public function updated(Contact $contact, ?User $actor, array $changes, array $original, float $startedAt): void
    {
        $diff = $this->diff($contact, $changes, $original);

        if (empty($diff)) {
            return;
        }

        $this->persist($contact, $actor, 'contact.updated', $diff);
        $this->logEvent('contact.updated', $contact, $actor, $startedAt, $diff);
    }

    public function deleted(Contact $contact, ?User $actor, float $startedAt): void
    {
        $payload = $this->snapshotPayload($contact);

        $this->persist($contact, $actor, 'contact.deleted', $payload);
        $this->logEvent('contact.deleted', $contact, $actor, $startedAt, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function persist(Contact $contact, ?User $actor, string $action, array $payload): void
    {
        AuditLog::create([
            'tenant_id' => $contact->tenant_id,
            'brand_id' => $actor?->brand_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => Contact::class,
            'auditable_id' => $contact->getKey(),
            'changes' => $payload,
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

            if ($field === 'gdpr_consent') {
                $diff['gdpr_consent'] = [
                    'old' => (bool) ($original['gdpr_consent'] ?? false),
                    'new' => (bool) $contact->gdpr_consent,
                ];
                continue;
            }

            if ($field === 'gdpr_consented_at') {
                $diff['gdpr_consented_at'] = [
                    'old' => $this->formatDate($original['gdpr_consented_at'] ?? null),
                    'new' => optional($contact->gdpr_consented_at)->toIso8601String(),
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

            if ($field === 'tags') {
                $diff['tags'] = [
                    'old' => $this->tagSlugs($original['tags'] ?? []),
                    'new' => $this->tagSlugs($contact->tags),
                ];
                continue;
            }

            if ($field === 'gdpr_notes') {
                $diff['gdpr_notes_digest'] = [
                    'old' => $this->hashValue($original['gdpr_notes'] ?? null),
                    'new' => $this->hashValue($contact->gdpr_notes),
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

    /**
     * @return array<string, mixed>
     */
    protected function snapshotPayload(Contact $contact): array
    {
        return [
            'snapshot' => [
                'name' => $contact->name,
                'company_id' => $contact->company_id,
                'gdpr_consent' => (bool) $contact->gdpr_consent,
                'gdpr_consented_at' => optional($contact->gdpr_consented_at)->toIso8601String(),
                'gdpr_consent_method' => $contact->gdpr_consent_method,
                'gdpr_consent_source' => $contact->gdpr_consent_source,
                'tags' => $this->tagSlugs($contact->tags),
                'gdpr_notes_digest' => $this->hashValue($contact->gdpr_notes),
            ],
            'sensitive' => [
                'email_hash' => $this->hashValue($contact->email),
                'phone_hash' => $this->hashValue($contact->phone),
                'metadata_keys' => array_keys($contact->metadata ?? []),
            ],
        ];
    }

    protected function hashValue(?string $value): string
    {
        return hash('sha256', (string) $value);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logEvent(string $action, Contact $contact, ?User $actor, float $startedAt, array $payload): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;
        $correlationId = request()?->header('X-Correlation-ID');

        Log::channel(config('logging.default'))->info($action, [
            'contact_id' => $contact->getKey(),
            'tenant_id' => $contact->tenant_id,
            'company_id' => $contact->company_id,
            'changes_keys' => array_keys($payload),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor?->getKey(),
            'context' => 'contact_audit',
            'correlation_id' => $correlationId,
            'gdpr_consent' => (bool) $contact->gdpr_consent,
        ]);
    }

    /**
     * @param  iterable<int, mixed>|null  $tags
     * @return array<int, string>
     */
    protected function tagSlugs(iterable $tags = null): array
    {
        if ($tags === null) {
            return [];
        }

        $collection = collect($tags);

        if ($collection->isEmpty()) {
            return [];
        }

        if ($collection->first() instanceof \App\Models\ContactTag) {
            return $collection->map(fn ($tag) => $tag->slug)->sort()->values()->all();
        }

        return $collection->map(fn ($tag) => (string) $tag)->sort()->values()->all();
    }

    protected function formatDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->toIso8601String();
        }

        return \Illuminate\Support\Carbon::parse($value)->toIso8601String();
    }
}
