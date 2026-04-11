<?php

namespace Database\Factories;

use App\Models\Ignore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ignore>
 */
class IgnoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ignorer_id' => User::factory(),
            'ignored_id' => User::factory(),
            'expires_at' => now()->addDay(),
        ];
    }
}
