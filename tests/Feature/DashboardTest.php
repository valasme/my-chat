<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Ignore;
use App\Models\Message;
use App\Models\Trash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_dashboard_shows_correct_stats_counts(): void
    {
        $user = User::factory()->create();
        $others = User::factory(5)->create();

        // 3 accepted contacts
        foreach ($others->take(3) as $other) {
            Contact::factory()->accepted()->create([
                'user_id' => $user->id,
                'contact_user_id' => $other->id,
            ]);
            $ids = [min($user->id, $other->id), max($user->id, $other->id)];
            Conversation::factory()->create([
                'user_one_id' => $ids[0],
                'user_two_id' => $ids[1],
            ]);
        }

        // 1 block
        Block::factory()->create(['blocker_id' => $user->id, 'blocked_id' => $others[3]->id]);

        // 1 active ignore
        Ignore::factory()->create([
            'ignorer_id' => $user->id,
            'ignored_id' => $others[4]->id,
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('contactsCount', 3);
        $response->assertViewHas('conversationsCount', 3);
        $response->assertViewHas('blocksCount', 1);
        $response->assertViewHas('ignoresCount', 1);
    }

    public function test_dashboard_shows_incoming_pending_requests(): void
    {
        $user = User::factory()->create();
        $requester = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $requester->id,
            'contact_user_id' => $user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee($requester->name);
        $response->assertSee(__('Accept'));
    }

    public function test_dashboard_shows_recent_conversations(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Contact::factory()->accepted()->create([
            'user_id' => $user->id,
            'contact_user_id' => $other->id,
        ]);

        $ids = [min($user->id, $other->id), max($user->id, $other->id)];
        $conversation = Conversation::factory()->create([
            'user_one_id' => $ids[0],
            'user_two_id' => $ids[1],
        ]);

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => 'Hello dashboard test',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee($other->name);
        $response->assertSee('Hello dashboard test');
    }

    public function test_dashboard_shows_expiring_ignores(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Contact::factory()->accepted()->create([
            'user_id' => $user->id,
            'contact_user_id' => $other->id,
        ]);

        Ignore::factory()->create([
            'ignorer_id' => $user->id,
            'ignored_id' => $other->id,
            'expires_at' => now()->addHours(12),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee($other->name);
        $response->assertSee(__('Ignore'));
    }

    public function test_dashboard_shows_expiring_trash(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $contact = Contact::factory()->accepted()->create([
            'user_id' => $user->id,
            'contact_user_id' => $other->id,
        ]);

        Trash::factory()->create([
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->addDays(3),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee($other->name);
        $response->assertSee(__('Trash'));
    }

    public function test_dashboard_excludes_ignored_conversations_from_count(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Contact::factory()->accepted()->create([
            'user_id' => $user->id,
            'contact_user_id' => $other->id,
        ]);

        $ids = [min($user->id, $other->id), max($user->id, $other->id)];
        Conversation::factory()->create([
            'user_one_id' => $ids[0],
            'user_two_id' => $ids[1],
        ]);

        Ignore::factory()->create([
            'ignorer_id' => $user->id,
            'ignored_id' => $other->id,
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertViewHas('conversationsCount', 0);
    }

    public function test_dashboard_empty_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('contactsCount', 0);
        $response->assertViewHas('conversationsCount', 0);
        $response->assertViewHas('blocksCount', 0);
        $response->assertViewHas('ignoresCount', 0);
        $response->assertSee(__('No conversations yet.'));
    }
}
