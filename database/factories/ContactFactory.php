<?php

/**
 * Contact Factory
 *
 * Generates fake Contact records for testing and seeding.
 * By default, creates two fresh users as owner and contact person.
 *
 * Usage in tests:
 *   Contact::factory()->create()                                   — two new users
 *   Contact::factory()->create(['user_id' => $user->id])           — specific owner
 *   Contact::factory()->count(5)->create(['user_id' => $user->id]) — bulk create
 *
 * @see \App\Models\Contact
 */

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Both user_id and contact_id reference User::factory() so each
     * Contact gets its own unique pair of users by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'contact_id' => User::factory(),
        ];
    }
}
