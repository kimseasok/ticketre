<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function view(User $user, Contact $contact): bool
    {
        return $user->can('contacts.manage') && $this->withinScope($user, $contact);
    }

    public function viewAny(User $user): bool
    {
        return $user->can('contacts.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('contacts.manage');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->can('contacts.manage') && $this->withinScope($user, $contact);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->can('contacts.manage') && $this->withinScope($user, $contact);
    }

    protected function withinScope(User $user, Contact $contact): bool
    {
        if ($contact->tenant_id !== $user->tenant_id) {
            return false;
        }

        if ($contact->brand_id && $user->brand_id && $contact->brand_id !== $user->brand_id) {
            return false;
        }

        return true;
    }
}
