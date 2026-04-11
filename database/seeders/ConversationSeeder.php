<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    /**
     * Create a conversation for every accepted contact.
     * Conversation uses canonical ordering: user_one_id = min, user_two_id = max.
     */
    public function run(): void
    {
        Contact::where('status', 'accepted')->each(function (Contact $contact) {
            $userOneId = min($contact->user_id, $contact->contact_user_id);
            $userTwoId = max($contact->user_id, $contact->contact_user_id);

            Conversation::firstOrCreate([
                'user_one_id' => $userOneId,
                'user_two_id' => $userTwoId,
            ]);
        });
    }
}
