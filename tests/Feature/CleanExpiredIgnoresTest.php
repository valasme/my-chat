<?php

namespace Tests\Feature;

use App\Models\Ignore;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CleanExpiredIgnoresTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_expired_ignores_are_deleted(): void
    {
        $ignorer = User::factory()->create();
        $ignored = User::factory()->create();

        Ignore::factory()->create([
            'ignorer_id' => $ignorer->id,
            'ignored_id' => $ignored->id,
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('app:clean-expired-ignores')
            ->expectsOutputToContain('Deleted 1 expired ignore(s)')
            ->assertExitCode(0);

        $this->assertDatabaseCount('ignores', 0);
    }

    public function test_active_ignores_are_not_deleted(): void
    {
        $ignorer = User::factory()->create();
        $ignored = User::factory()->create();

        Ignore::factory()->create([
            'ignorer_id' => $ignorer->id,
            'ignored_id' => $ignored->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->artisan('app:clean-expired-ignores')
            ->expectsOutputToContain('Deleted 0 expired ignore(s)')
            ->assertExitCode(0);

        $this->assertDatabaseCount('ignores', 1);
    }
}
