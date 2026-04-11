<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_read_routes_have_rate_limiting(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('contacts.index'));

        $response->assertStatus(200);
        $response->assertHeader('X-RateLimit-Limit', 120);
    }

    public function test_write_routes_have_rate_limiting(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('contacts.store'), [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertHeader('X-RateLimit-Limit', 30);
    }

    public function test_message_routes_have_rate_limiting(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('messages.store', ['conversation' => 999]), [
            'body' => 'test',
        ]);

        $response->assertHeader('X-RateLimit-Limit', 60);
    }
}
