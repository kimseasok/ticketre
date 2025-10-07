<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($data, $actor) {
            $startedAt = microtime(true);

            $tags = Arr::pull($data, 'tags', []);

            $this->normalizeGdprConsent($data);

            $contact = Contact::create($data);
            $contact->refresh();

            if (! empty($tags)) {
                $contact->tags()->sync($tags);
            }

            $contact->load('tags');

            $this->auditLogger->created($contact, $actor, $startedAt);

            return $contact;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Contact $contact, array $data, User $actor): Contact
    {
        return DB::transaction(function () use ($contact, $data, $actor) {
            $startedAt = microtime(true);

            $tags = Arr::pull($data, 'tags', null);

            $this->normalizeGdprConsent($data, $contact);

            $contact->fill($data);
            $dirty = Arr::except($contact->getDirty(), ['updated_at']);
            $original = Arr::only($contact->getOriginal(), array_keys($dirty));

            $originalTags = null;

            $contact->save();

            if (is_array($tags)) {
                $originalTags = $contact->tags()->pluck('contact_tags.slug')->all();
                $contact->tags()->sync($tags);
            }

            $contact->refresh()->load('tags');

            if (is_array($tags)) {
                $dirty['tags'] = $contact->tags->pluck('slug')->all();
                $original['tags'] = $originalTags;
            }

            if (! empty($dirty) || is_array($tags)) {
                $this->auditLogger->updated($contact, $actor, $dirty, $original, $startedAt);
            }

            return $contact;
        });
    }

    public function delete(Contact $contact, User $actor): void
    {
        $startedAt = microtime(true);

        $contact->delete();

        $this->auditLogger->deleted($contact, $actor, $startedAt);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function normalizeGdprConsent(array &$data, ?Contact $contact = null): void
    {
        if (array_key_exists('gdpr_consent', $data)) {
            $consented = (bool) $data['gdpr_consent'];

            if ($consented) {
                $data['gdpr_consent'] = true;
                $data['gdpr_consented_at'] = $data['gdpr_consented_at'] ?? now();
            } else {
                $data['gdpr_consent'] = false;
                $data['gdpr_consented_at'] = null;
                $data['gdpr_consent_method'] = $data['gdpr_consent_method'] ?? null;
                $data['gdpr_consent_source'] = $data['gdpr_consent_source'] ?? null;
            }
        } elseif ($contact) {
            $data['gdpr_consent'] = $contact->gdpr_consent;
        }
    }
}
