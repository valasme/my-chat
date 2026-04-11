<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Trash;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trash>
 */
class TrashFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'contact_id' => Contact::factory(),
            'expires_at' => now()->addDays(7),
            'is_quick_delete' => false,
        ];
    }
}
