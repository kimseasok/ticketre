<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Arr;

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

        $contact = Contact::create($data);
        $contact->refresh();

        $this->auditLogger->created($contact, $actor, $startedAt);

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Contact $contact, array $data, User $actor): Contact
    {
        $startedAt = microtime(true);

        $contact->fill($data);
        $dirty = Arr::except($contact->getDirty(), ['updated_at']);
        $original = Arr::only($contact->getOriginal(), array_keys($dirty));
        $contact->save();

        $contact->refresh();

        if (! empty($dirty)) {
            $this->auditLogger->updated($contact, $actor, $dirty, $original, $startedAt);
        }

        return $contact;
    }

    public function delete(Contact $contact, User $actor): void
    {
        $startedAt = microtime(true);

        $contact->delete();

        $this->auditLogger->deleted($contact, $actor, $startedAt);
    }
}
