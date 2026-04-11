<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $me = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $users = User::factory(50)->create();

        $this->call([
            ContactSeeder::class,
            ConversationSeeder::class,
            MessageSeeder::class,
            BlockSeeder::class,
            IgnoreSeeder::class,
            TrashSeeder::class,
        ]);
    }
}
