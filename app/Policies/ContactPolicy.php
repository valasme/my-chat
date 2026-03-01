<?php

/**
 * Contact Authorization Policy
 *
 * Determines which actions a user may perform on Contact records.
 * Registered automatically by Laravel's policy auto-discovery because
 * it follows the App\Policies\{Model}Policy naming convention.
 *
 * Rules:
 *   - viewAny / create: Any authenticated user can list and add contacts.
 *   - view / delete: Only the contact owner (user_id) may access or remove it.
 *
 * @see \App\Models\Contact
 * @see \App\Http\Controllers\ContactController
 */

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    /**
     * Determine whether the user can view the contacts list.
     *
     * Any authenticated user may browse their own contacts.
     * The controller scopes the query to the current user.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific contact.
     *
     * Ensures the authenticated user owns the contact record,
     * preventing horizontal privilege escalation.
     */
    public function view(User $user, Contact $contact): bool
    {
        return $user->id === $contact->user_id;
    }

    /**
     * Determine whether the user can create new contacts.
     *
     * Any authenticated user may add contacts. Business logic
     * (self-add, duplicate) is handled in the controller.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the contact.
     *
     * Only the contact owner may remove it from their list.
     */
    public function delete(User $user, Contact $contact): bool
    {
        return $user->id === $contact->user_id;
    }
}
