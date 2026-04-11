<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    /**
     * Seed the application's database with contacts.
     *
     * Distribution for "me" (test@example.com):
     * - 15 accepted contacts (IDs 2–16)
     * - 5 incoming pending requests (IDs 17–21)
     * - 5 outgoing pending requests (IDs 22–26)
     * - Users 27–31: reserved for blocks (BlockSeeder)
     * - Users 32–36: reserved for ignores (IgnoreSeeder)
     * - Users 37–41: reserved for trash (TrashSeeder)
     * - Users 42–51: strangers (no relationship)
     *
     * Cross-user: ~20 random accepted contacts between non-"me" users.
     */
    public function run(): void
    {
        $me = User::where('email', 'test@example.com')->firstOrFail();
        $others = User::where('id', '!=', $me->id)->orderBy('id')->get();

        // 15 accepted contacts for "me" (users index 0–14 → IDs 2–16)
        foreach ($others->slice(0, 15) as $user) {
            Contact::factory()->accepted()->create([
                'user_id' => $me->id,
                'contact_user_id' => $user->id,
            ]);
        }

        // 5 incoming pending requests to "me" (users index 15–19 → IDs 17–21)
        foreach ($others->slice(15, 5) as $user) {
            Contact::factory()->pending()->create([
                'user_id' => $user->id,
                'contact_user_id' => $me->id,
            ]);
        }

        // 5 outgoing pending requests from "me" (users index 20–24 → IDs 22–26)
        foreach ($others->slice(20, 5) as $user) {
            Contact::factory()->pending()->create([
                'user_id' => $me->id,
                'contact_user_id' => $user->id,
            ]);
        }

        // Ignore targets need accepted contacts (users index 30–34 → IDs 32–36)
        // 3 I'm ignoring + 2 ignoring me — all need accepted contacts
        foreach ($others->slice(30, 5) as $user) {
            Contact::factory()->accepted()->create([
                'user_id' => $me->id,
                'contact_user_id' => $user->id,
            ]);
        }

        // Trash targets need accepted contacts (users index 35–39 → IDs 37–41)
        // 3 in my trash + 1 quick-delete — all need accepted contacts
        foreach ($others->slice(35, 4) as $user) {
            Contact::factory()->accepted()->create([
                'user_id' => $me->id,
                'contact_user_id' => $user->id,
            ]);
        }

        // Cross-user contacts: ~20 random accepted contacts between other users
        $crossUsers = $others->slice(0, 30)->values();
        $created = 0;
        for ($i = 0; $i < $crossUsers->count() - 1 && $created < 20; $i++) {
            $partner = $crossUsers[$i + 1];
            Contact::factory()->accepted()->create([
                'user_id' => $crossUsers[$i]->id,
                'contact_user_id' => $partner->id,
            ]);
            $created++;
        }
    }
}
