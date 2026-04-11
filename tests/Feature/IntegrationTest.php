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

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_block_while_trashed_cleans_everything(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userA->id,
            'body' => 'Hello',
        ]);

        Trash::create([
            'user_id' => $userA->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->addDays(7),
        ]);

        Ignore::create([
            'ignorer_id' => $userA->id,
            'ignored_id' => $userB->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($userA)
            ->post(route('blocks.store'), ['user_id' => $userB->id]);

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('trashes', 0);
        $this->assertDatabaseCount('ignores', 0);
        $this->assertDatabaseHas('blocks', [
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);
    }

    public function test_full_lifecycle_request_accept_message_delete(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Send request
        $this->actingAs($userA)
            ->post(route('contacts.store'), ['email' => $userB->email]);

        $contact = Contact::first();
        $this->assertEquals('pending', $contact->status);

        // Accept
        $this->actingAs($userB)
            ->put(route('contacts.update', $contact), ['action' => 'accept']);

        $contact->refresh();
        $this->assertEquals('accepted', $contact->status);

        // Conversation created
        $conversation = Conversation::first();
        $this->assertNotNull($conversation);

        // Send messages
        $this->actingAs($userA)
            ->post(route('messages.store', $conversation), ['body' => 'Hi!']);

        $this->actingAs($userB)
            ->post(route('messages.store', $conversation), ['body' => 'Hey!']);

        $this->assertDatabaseCount('messages', 2);

        // Delete contact
        $this->actingAs($userA)
            ->delete(route('contacts.destroy', $contact));

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_unblock_allows_new_request(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $block = Block::create([
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);

        // Blocked user can't request
        $this->actingAs($userB)
            ->post(route('contacts.store'), ['email' => $userA->email])
            ->assertSessionHasErrors('email');

        // Unblock
        $this->actingAs($userA)
            ->delete(route('blocks.destroy', $block));

        // Now the user can send a request
        $this->actingAs($userB)
            ->post(route('contacts.store'), ['email' => $userA->email])
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseHas('contacts', [
            'user_id' => $userB->id,
            'contact_user_id' => $userA->id,
            'status' => 'pending',
        ]);
    }

    public function test_user_deletion_cascades_contacts(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userA->id,
            'body' => 'Test',
        ]);

        $userA->delete();

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }
}
