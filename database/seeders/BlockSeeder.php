<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlockSeeder extends Seeder
{
    /**
     * Seed blocks.
     *
     * Users index 25–27 (IDs ~27–29): I blocked them.
     * Users index 28–29 (IDs ~30–31): They blocked me.
     *
     * Blocked pairs should NOT have contacts or conversations (per app logic,
     * blocking cascades and deletes everything). These user slots were NOT given
     * contacts in ContactSeeder.
     */
    public function run(): void
    {
        $me = User::where('email', 'test@example.com')->firstOrFail();
        $others = User::where('id', '!=', $me->id)->orderBy('id')->get();

        // 3 users I blocked (index 25–27)
        foreach ($others->slice(25, 3) as $user) {
            Block::create([
                'blocker_id' => $me->id,
                'blocked_id' => $user->id,
            ]);
        }

        // 2 users who blocked me (index 28–29)
        foreach ($others->slice(28, 2) as $user) {
            Block::create([
                'blocker_id' => $user->id,
                'blocked_id' => $me->id,
            ]);
        }
    }
}
