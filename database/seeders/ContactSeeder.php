<?php

/**
 * Contact Seeder
 *
 * Seeds the contacts table with sample user-to-user relationships.
 * Ensures at least 5 users exist, then creates 3 random contact
 * links for each user (avoids self-referencing).
 *
 * Run with: php artisan db:seed --class=ContactSeeder
 *
 * @see \App\Models\Contact
 * @see \Database\Factories\ContactFactory
 */

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    /**
     * Minimum number of users required for meaningful contact data.
     */
    private const MIN_USERS = 5;

    /**
     * Number of contacts to create per user.
     */
    private const CONTACTS_PER_USER = 3;

    /**
     * Run the database seeds.
     *
     * Creates missing users up to the minimum threshold, then
     * assigns random contacts to each user while preventing
     * self-referencing entries.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->count() < self::MIN_USERS) {
            $users = $users->merge(
                User::factory(self::MIN_USERS - $users->count())->create()
            );
        }

        $users->each(function (User $owner) use ($users) {
            $others = $users->where('id', '!=', $owner->id)
                ->random(min(self::CONTACTS_PER_USER, $users->count() - 1));

            foreach ($others as $other) {
                Contact::factory()->create([
                    'user_id' => $owner->id,
                    'contact_id' => $other->id,
                ]);
            }
        });
    }
}
