<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
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
            'contact_id' => null,
            'title' => fake()->sentence(4),
            'body' => fake()->paragraphs(2, true),
            'tags' => fake()->randomElements(
                ['work', 'personal', 'important', 'todo', 'meeting', 'follow-up'],
                fake()->numberBetween(0, 3)
            ),
        ];
    }

    /**
     * Indicate that the note is not linked to a contact (default state made explicit for readability).
     */
    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => null,
        ]);
    }

    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
