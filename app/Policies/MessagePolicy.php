<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Ignore;
use App\Models\Trash;
use App\Models\User;

class MessagePolicy
{
    public function create(User $user, Conversation $conversation): bool
    {
        if (! $conversation->hasParticipant($user->id)) {
            return false;
        }

        $otherUserId = $conversation->user_one_id === $user->id
            ? $conversation->user_two_id
            : $conversation->user_one_id;

        if ($user->hasBlockedUser(User::find($otherUserId)) || $user->isBlockedByUser(User::find($otherUserId))) {
            return false;
        }

        if (Ignore::where('ignorer_id', $otherUserId)->where('ignored_id', $user->id)->active()->exists()) {
            return false;
        }

        $contact = Contact::between($user->id, $otherUserId)->accepted()->first();
        if (! $contact) {
            return false;
        }

        if (Trash::where('user_id', $user->id)->where('contact_id', $contact->id)->exists()) {
            return false;
        }

        return true;
    }
}
