<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Trash;
use App\Models\User;
use Illuminate\Database\Seeder;

class TrashSeeder extends Seeder
{
    /**
     * Seed trash entries for "me".
     *
     * Users index 35–38 (IDs ~37–41): contacts in my trash.
     * - 3 normal trash with varied expiry (7d, 14d, 30d)
     * - 1 quick-delete (messages wiped)
     *
     * All these users have accepted contacts (set up in ContactSeeder).
     */
    public function run(): void
    {
        $me = User::where('email', 'test@example.com')->firstOrFail();
        $others = User::where('id', '!=', $me->id)->orderBy('id')->get();

        $trashUsers = $others->slice(35, 4)->values();
        $expiryDays = [7, 14, 30];

        // 3 normal trash entries
        foreach ($trashUsers->take(3) as $i => $user) {
            $contact = Contact::where(function ($q) use ($me, $user) {
                $q->where('user_id', $me->id)->where('contact_user_id', $user->id);
            })->orWhere(function ($q) use ($me, $user) {
                $q->where('user_id', $user->id)->where('contact_user_id', $me->id);
            })->firstOrFail();

            Trash::create([
                'user_id' => $me->id,
                'contact_id' => $contact->id,
                'expires_at' => now()->addDays($expiryDays[$i]),
                'is_quick_delete' => false,
            ]);
        }

        // 1 quick-delete entry (wipes messages from my side)
        $quickDeleteUser = $trashUsers[3];
        $contact = Contact::where(function ($q) use ($me, $quickDeleteUser) {
            $q->where('user_id', $me->id)->where('contact_user_id', $quickDeleteUser->id);
        })->orWhere(function ($q) use ($me, $quickDeleteUser) {
            $q->where('user_id', $quickDeleteUser->id)->where('contact_user_id', $me->id);
        })->firstOrFail();

        Trash::create([
            'user_id' => $me->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->addDays(30),
            'is_quick_delete' => true,
        ]);

        // For quick-delete, wipe messages from the conversation
        $userOneId = min($me->id, $quickDeleteUser->id);
        $userTwoId = max($me->id, $quickDeleteUser->id);
        $conversation = Conversation::where('user_one_id', $userOneId)
            ->where('user_two_id', $userTwoId)
            ->first();

        if ($conversation) {
            Message::where('conversation_id', $conversation->id)->delete();
        }
    }
}
