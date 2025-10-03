<?php

use App\Models\Contact;

it('manages contacts', function () {
    $contact = Contact::factory()->create(['name' => 'Original']);
    expect($contact->name)->toBe('Original');

    $contact->update(['name' => 'Updated']);
    expect($contact->fresh()->name)->toBe('Updated');

    $contact->delete();
    expect($contact->trashed())->toBeTrue();
});
