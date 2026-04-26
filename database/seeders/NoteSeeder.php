<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Seeder;

class NoteSeeder extends Seeder
{
    /**
     * Seed notes for the test user:
     * - 8 personal notes
     * - 5 contact-linked notes (first 5 accepted contacts)
     * - 2 trashed notes
     */
    public function run(): void
    {
        $me = User::where('email', 'test@example.com')->firstOrFail();

        Note::factory()->count(8)->create([
            'user_id' => $me->id,
            'contact_id' => null,
        ]);

        $contacts = Contact::where('user_id', $me->id)
            ->where('status', 'accepted')
            ->take(5)
            ->get();

        foreach ($contacts as $contact) {
            Note::factory()->create([
                'user_id' => $me->id,
                'contact_id' => $contact->id,
            ]);
        }

        Note::factory()->trashed()->count(2)->create([
            'user_id' => $me->id,
            'contact_id' => null,
        ]);
    }
}
