<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->count() < 5) {
            $users = $users->merge(User::factory(5 - $users->count())->create());
        }

        $users->each(function (User $owner) use ($users) {
            $others = $users->where('id', '!=', $owner->id)->random(min(3, $users->count() - 1));

            foreach ($others as $other) {
                Contact::factory()->create([
                    'user_id' => $owner->id,
                    'contact_id' => $other->id,
                ]);
            }
        });
    }
}
