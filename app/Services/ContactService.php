<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContactService
{
    public function __construct(private readonly ContactAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?string $correlationId = null): Contact
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data, $actor);
        $correlation = $this->resolveCorrelationId($correlationId);

        /** @var Contact $contact */
        $contact = DB::transaction(function () use ($attributes) {
            return Contact::create($attributes);
        });

        $contact->refresh();

        $this->auditLogger->created($contact, $actor, $startedAt, $correlation);
        $this->logPerformance('contact.create', $contact, $actor, $startedAt, $correlation);

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Contact $contact, array $data, User $actor, ?string $correlationId = null): Contact
    {
        $startedAt = microtime(true);
        $attributes = $this->prepareAttributes($data, $actor, $contact);
        $correlation = $this->resolveCorrelationId($correlationId);

        $original = Arr::only($contact->getOriginal(), [
            'name',
            'email',
            'phone',
            'company_id',
            'brand_id',
            'metadata',
            'tags',
            'gdpr_marketing_opt_in',
            'gdpr_data_processing_opt_in',
        ]);

        $dirty = [];

        DB::transaction(function () use ($contact, $attributes, &$dirty) {
            $contact->fill($attributes);
            $dirty = Arr::except($contact->getDirty(), ['updated_at']);
            $contact->save();
        });

        $contact->refresh();

        $this->auditLogger->updated($contact, $actor, $dirty, $original, $startedAt, $correlation);
        $this->logPerformance('contact.update', $contact, $actor, $startedAt, $correlation, array_keys($dirty));

        return $contact;
    }

    public function delete(Contact $contact, User $actor, ?string $correlationId = null): void
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        DB::transaction(function () use ($contact) {
            $contact->delete();
        });

        $this->auditLogger->deleted($contact, $actor, $startedAt, $correlation);
        $this->logPerformance('contact.delete', $contact, $actor, $startedAt, $correlation);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, User $actor, ?Contact $contact = null): array
    {
        $attributes = Arr::only($data, [
            'name',
            'email',
            'phone',
            'company_id',
            'brand_id',
            'metadata',
            'tags',
            'gdpr_marketing_opt_in',
            'gdpr_data_processing_opt_in',
        ]);

        if (! array_key_exists('tenant_id', $attributes)) {
            $attributes['tenant_id'] = $contact?->tenant_id
                ?? (app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : $actor->tenant_id);
        }

        if (! array_key_exists('brand_id', $attributes)) {
            $attributes['brand_id'] = $contact?->brand_id
                ?? ($data['brand_id'] ?? (app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : $actor->brand_id));
        }

        if (array_key_exists('company_id', $attributes) && $attributes['company_id']) {
            $company = Company::query()->find($attributes['company_id']);

            if ($company) {
                $attributes['company_id'] = $company->getKey();

                if (empty($attributes['brand_id'])) {
                    $attributes['brand_id'] = $company->brand_id;
                }
            }
        }

        $attributes['tags'] = $this->normaliseTags($attributes['tags'] ?? ($contact?->tags ?? []));
        $attributes['metadata'] = $this->normaliseMetadata($attributes['metadata'] ?? ($contact?->metadata ?? []));

        if (array_key_exists('gdpr_marketing_opt_in', $attributes)) {
            $attributes['gdpr_marketing_opt_in'] = (bool) $attributes['gdpr_marketing_opt_in'];
        }

        if (array_key_exists('gdpr_data_processing_opt_in', $attributes)) {
            $attributes['gdpr_data_processing_opt_in'] = (bool) $attributes['gdpr_data_processing_opt_in'];
        }

        return $attributes;
    }

    /**
     * @param  array<int, string>|string|null  $value
     * @return array<int, string>
     */
    protected function normaliseTags(array|string|null $value): array
    {
        $tags = is_array($value) ? $value : (is_string($value) ? [$value] : []);

        $normalised = [];
        foreach ($tags as $tag) {
            $trimmed = trim((string) $tag);

            if ($trimmed === '') {
                continue;
            }

            $normalised[] = Str::limit(Str::lower($trimmed), 64, '');
        }

        return array_values(array_unique($normalised));
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    protected function normaliseMetadata(array $metadata): array
    {
        $sanitised = [];

        foreach ($metadata as $key => $value) {
            $stringKey = Str::limit((string) $key, 64, '');
            $sanitised[$stringKey] = $value;
        }

        return $sanitised;
    }

    protected function resolveCorrelationId(?string $value): string
    {
        $header = request()?->header('X-Correlation-ID');
        $candidate = $value ?? $header ?? (string) Str::uuid();

        return Str::limit($candidate, 64, '');
    }

    /**
     * @param  array<int, string>  $fields
     */
    protected function logPerformance(string $action, Contact $contact, User $actor, float $startedAt, string $correlationId, array $fields = []): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;

        Log::channel(config('logging.default'))->info($action, [
            'contact_id' => $contact->getKey(),
            'tenant_id' => $contact->tenant_id,
            'brand_id' => $contact->brand_id,
            'company_id' => $contact->company_id,
            'tags_count' => count($contact->tags ?? []),
            'duration_ms' => round($durationMs, 2),
            'user_id' => $actor->getKey(),
            'changed_fields' => $fields,
            'correlation_id' => $correlationId,
            'context' => 'contact_service',
        ]);
    }
}
