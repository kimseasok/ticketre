<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ContactService
{
    public function __construct(private readonly ContactAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Contact
    {
        $startedAt = microtime(true);

        $payload = $this->preparePayload($data, true);

        /** @var array<string, mixed> $attributes */
        $attributes = $payload['attributes'];
        /** @var array<int, string> $tags */
        $tags = $payload['tags'];

        $contact = Contact::create($attributes);
        $contact->refresh();

        $this->syncTags($contact, $tags);
        $contact->load(['company', 'tags']);

        $this->auditLogger->created($contact, $actor, $startedAt);

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Contact $contact, array $data, User $actor): Contact
    {
        $startedAt = microtime(true);

        $payload = $this->preparePayload($data, false, $contact);

        /** @var array<string, mixed> $attributes */
        $attributes = $payload['attributes'];
        /** @var array<int, string>|null $tags */
        $tags = $payload['tags'];

        $previousTags = $tags !== null
            ? $contact->tags()->pluck('contact_tags.name')->sort()->values()->all()
            : [];

        $contact->fill($attributes);
        $dirty = Arr::except($contact->getDirty(), ['updated_at']);
        $original = Arr::only($contact->getOriginal(), array_keys($dirty));

        if ($tags !== null) {
            $original['tags'] = $previousTags;
        }

        $contact->save();
        $contact->refresh();

        if ($tags !== null) {
            $this->syncTags($contact, $tags);
            $dirty['tags'] = $contact->tags()->pluck('name')->sort()->values()->all();
        }

        $contact->load(['company', 'tags']);

        if (! empty($dirty)) {
            $this->auditLogger->updated($contact, $actor, $dirty, $original, $startedAt);
        }

        return $contact;
    }

    public function delete(Contact $contact, User $actor): void
    {
        $startedAt = microtime(true);

        $contact->loadMissing(['company', 'tags']);
        $contact->delete();

        $this->auditLogger->deleted($contact, $actor, $startedAt);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{attributes: array<string, mixed>, tags: array<int, string>|null}
     */
    protected function preparePayload(array $data, bool $forceTags = false, ?Contact $contact = null): array
    {
        $attributes = Arr::except($data, ['tags']);

        if (array_key_exists('metadata', $attributes)) {
            $attributes['metadata'] = $this->normalizeMetadata($attributes['metadata']);
        }

        $this->applyGdprConsent($attributes, $contact);

        $tags = null;

        if ($forceTags || array_key_exists('tags', $data)) {
            $tags = $this->normalizeTags($data['tags'] ?? []);
        }

        return [
            'attributes' => $attributes,
            'tags' => $tags,
        ];
    }

    /**
     * @param  mixed  $metadata
     * @return array<string, mixed>
     */
    protected function normalizeMetadata(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        /** @var array<string, mixed> $filtered */
        $filtered = collect($metadata)
            ->filter(fn ($value): bool => $value !== null)
            ->toArray();

        return $filtered;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function applyGdprConsent(array &$attributes, ?Contact $contact = null): void
    {
        $marketing = array_key_exists('gdpr_marketing_opt_in', $attributes)
            ? (bool) $attributes['gdpr_marketing_opt_in']
            : ($contact?->gdpr_marketing_opt_in ?? false);

        $tracking = array_key_exists('gdpr_tracking_opt_in', $attributes)
            ? (bool) $attributes['gdpr_tracking_opt_in']
            : ($contact?->gdpr_tracking_opt_in ?? false);

        $attributes['gdpr_marketing_opt_in'] = $marketing;
        $attributes['gdpr_tracking_opt_in'] = $tracking;

        if (isset($attributes['gdpr_consent_recorded_at']) && $attributes['gdpr_consent_recorded_at']) {
            $attributes['gdpr_consent_recorded_at'] = Carbon::parse($attributes['gdpr_consent_recorded_at']);
            return;
        }

        if (! empty($attributes['gdpr_consent_recorded_at'])) {
            return;
        }

        $shouldRecord = ($marketing || $tracking)
            && (
                ! $contact
                || $contact->gdpr_marketing_opt_in !== $marketing
                || $contact->gdpr_tracking_opt_in !== $tracking
                || $contact->gdpr_consent_recorded_at === null
            );

        if ($shouldRecord) {
            $attributes['gdpr_consent_recorded_at'] = now();
        }
    }

    /**
     * @param  mixed  $tags
     * @return array<int, string>
     */
    protected function normalizeTags(mixed $tags): array
    {
        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        /** @var array<int, string> $normalized */
        $normalized = collect($tags)
            ->filter(fn ($value) => ! is_array($value))
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique(fn (string $tag) => mb_strtolower($tag))
            ->values()
            ->all();

        return $normalized;
    }

    /**
     * @param  array<int, string>  $tags
     */
    protected function syncTags(Contact $contact, array $tags): void
    {
        if (empty($tags)) {
            $contact->tags()->detach();

            return;
        }

        $tenantId = $contact->tenant_id;

        $tagRecords = collect($tags)
            ->map(function (string $tagName) use ($tenantId): ContactTag {
                $slug = Str::slug($tagName);

                if ($slug === '') {
                    $slug = Str::uuid()->toString();
                }

                $existing = ContactTag::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('slug', $slug)
                    ->first();

                if ($existing) {
                    $displayName = Str::title($tagName);

                    if ($existing->name !== $displayName) {
                        $existing->update(['name' => $displayName]);
                    }

                    return $existing;
                }

                /** @var ContactTag $tag */
                $tag = ContactTag::create([
                    'tenant_id' => $tenantId,
                    'name' => Str::title($tagName),
                    'slug' => $slug,
                ]);

                return $tag;
            })
            ->values();

        $contact->tags()->sync($tagRecords->pluck('id')->all());
    }
}
