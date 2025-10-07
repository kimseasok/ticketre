<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function view(User $user, Contact $contact): bool
    {
        return $user->can('contacts.manage') || $user->can('contacts.view');
    }

    public function viewAny(User $user): bool
    {
        return $user->can('contacts.manage') || $user->can('contacts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('contacts.manage');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->can('contacts.manage');
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->can('contacts.manage');
    }
}
