<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    /**
     * Determine whether the user can view any contacts.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the contact.
     */
    public function view(User $user, Contact $contact): bool
    {
        return $contact->involvesUser($user->id);
    }

    /**
     * Determine whether the user can create contacts.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the contact (accept/decline).
     */
    public function update(User $user, Contact $contact): bool
    {
        return $contact->contact_user_id === $user->id
            && $contact->status === 'pending';
    }

    /**
     * Determine whether the user can delete the contact.
     */
    public function delete(User $user, Contact $contact): bool
    {
        return $contact->involvesUser($user->id);
    }
}
