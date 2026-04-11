<?php

namespace Database\Seeders;

use App\Models\Ignore;
use App\Models\User;
use Illuminate\Database\Seeder;

class IgnoreSeeder extends Seeder
{
    /**
     * Seed ignores for "me".
     *
     * Users index 30–32 (IDs ~32–34): I'm ignoring them (varied durations).
     * Users index 33–34 (IDs ~35–36): They're ignoring me.
     *
     * All these users have accepted contacts (set up in ContactSeeder).
     */
    public function run(): void
    {
        $me = User::where('email', 'test@example.com')->firstOrFail();
        $others = User::where('id', '!=', $me->id)->orderBy('id')->get();

        // 3 users I'm ignoring with varied durations
        $durations = [
            now()->addHour(),      // 1 hour
            now()->addDays(3),     // 3 days
            now()->addDays(7),     // 7 days
        ];

        $ignoreTargets = $others->slice(30, 3)->values();
        foreach ($ignoreTargets as $i => $user) {
            Ignore::create([
                'ignorer_id' => $me->id,
                'ignored_id' => $user->id,
                'expires_at' => $durations[$i],
            ]);
        }

        // 2 users ignoring me with different durations
        $theirDurations = [
            now()->addHours(8),    // 8 hours
            now()->addDay(),       // 24 hours
        ];

        $ignorersOfMe = $others->slice(33, 2)->values();
        foreach ($ignorersOfMe as $i => $user) {
            Ignore::create([
                'ignorer_id' => $user->id,
                'ignored_id' => $me->id,
                'expires_at' => $theirDurations[$i],
            ]);
        }
    }
}
