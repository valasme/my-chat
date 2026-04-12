<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Ignore;
use App\Models\Message;
use App\Models\Trash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_conversations(): void
    {
        $this->get(route('conversations.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_conversations_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('conversations.index'))
            ->assertOk();
    }

    public function test_conversation_created_on_contact_accept(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($recipient)
            ->put(route('contacts.update', $contact), ['action' => 'accept']);

        $userOneId = min($sender->id, $recipient->id);
        $userTwoId = max($sender->id, $recipient->id);

        $this->assertDatabaseHas('conversations', [
            'user_one_id' => $userOneId,
            'user_two_id' => $userTwoId,
        ]);
    }

    public function test_user_can_view_own_conversation(): void
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

        $this->actingAs($userA)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertSee($userB->name);
    }

    public function test_user_cannot_view_others_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $outsider = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        $this->actingAs($outsider)
            ->get(route('conversations.show', $conversation))
            ->assertForbidden();
    }

    public function test_conversations_index_shows_user_conversations(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        $this->actingAs($userA)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee($userB->name);
    }

    public function test_conversations_index_excludes_ignored_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        foreach ([$userB, $userC] as $other) {
            Contact::factory()->create([
                'user_id' => $userA->id,
                'contact_user_id' => $other->id,
                'status' => 'accepted',
            ]);

            Conversation::create([
                'user_one_id' => min($userA->id, $other->id),
                'user_two_id' => max($userA->id, $other->id),
            ]);
        }

        Ignore::create([
            'ignorer_id' => $userA->id,
            'ignored_id' => $userB->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($userA)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertDontSee($userB->name)
            ->assertSee($userC->name);
    }

    public function test_conversations_index_excludes_trashed_contacts(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        foreach ([$userB, $userC] as $other) {
            $contact = Contact::factory()->create([
                'user_id' => $userA->id,
                'contact_user_id' => $other->id,
                'status' => 'accepted',
            ]);

            Conversation::create([
                'user_one_id' => min($userA->id, $other->id),
                'user_two_id' => max($userA->id, $other->id),
            ]);

            if ($other->id === $userB->id) {
                Trash::create([
                    'user_id' => $userA->id,
                    'contact_id' => $contact->id,
                    'expires_at' => now()->addDays(7),
                ]);
            }
        }

        $this->actingAs($userA)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertDontSee($userB->name)
            ->assertSee($userC->name);
    }

    public function test_conversations_index_sorts_az(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create(['name' => 'Alice']);
        $userC = User::factory()->create(['name' => 'Zara']);

        foreach ([$userB, $userC] as $other) {
            Contact::factory()->create([
                'user_id' => $userA->id,
                'contact_user_id' => $other->id,
                'status' => 'accepted',
            ]);

            Conversation::create([
                'user_one_id' => min($userA->id, $other->id),
                'user_two_id' => max($userA->id, $other->id),
            ]);
        }

        $response = $this->actingAs($userA)
            ->get(route('conversations.index', ['sort' => 'az']));

        $response->assertOk();
        $response->assertSeeInOrder(['Alice', 'Zara']);
    }

    public function test_conversations_index_rejects_invalid_sort(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('conversations.index', ['sort' => 'DROP TABLE']))
            ->assertOk();
    }

    public function test_conversation_show_paginates_messages(): void
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

        for ($i = 0; $i < 55; $i++) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $userA->id,
                'body' => "Message {$i}",
            ]);
        }

        // Without ?page, should auto-show last page (newest messages)
        $response = $this->actingAs($userA)
            ->get(route('conversations.show', $conversation));

        $response->assertOk();
        $response->assertSee('Message 54');
        $response->assertSee('Message 50');
        $response->assertDontSee('Message 0');

        // Explicitly requesting page 1 shows oldest messages
        $page1 = $this->actingAs($userA)
            ->get(route('conversations.show', ['conversation' => $conversation, 'page' => 1]));

        $page1->assertOk();
        $page1->assertSee('Message 0');
        $page1->assertDontSee('Message 54');
    }

    public function test_guests_cannot_view_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        $this->get(route('conversations.show', $conversation))
            ->assertRedirect(route('login'));
    }

    public function test_conversation_with_no_messages_shows_ok(): void
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

        $this->actingAs($userA)
            ->get(route('conversations.show', $conversation))
            ->assertOk();
    }

    public function test_conversations_index_sorts_za(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create(['name' => 'Alice']);
        $userC = User::factory()->create(['name' => 'Zara']);

        foreach ([$userB, $userC] as $other) {
            Contact::factory()->create([
                'user_id' => $userA->id,
                'contact_user_id' => $other->id,
                'status' => 'accepted',
            ]);

            Conversation::create([
                'user_one_id' => min($userA->id, $other->id),
                'user_two_id' => max($userA->id, $other->id),
            ]);
        }

        $response = $this->actingAs($userA)
            ->get(route('conversations.index', ['sort' => 'za']));

        $response->assertOk();
        $response->assertSeeInOrder(['Zara', 'Alice']);
    }

    public function test_conversation_shows_other_users_name(): void
    {
        $userA = User::factory()->create(['name' => 'Alice']);
        $userB = User::factory()->create(['name' => 'Bob']);

        Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        $this->actingAs($userA)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertSee('Bob');

        $this->actingAs($userB)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertSee('Alice');
    }

    public function test_conversations_index_default_sort_by_recent_message(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create(['name' => 'OlderContact']);
        $userC = User::factory()->create(['name' => 'NewerContact']);

        $conversations = [];
        foreach ([$userB, $userC] as $other) {
            Contact::factory()->create([
                'user_id' => $userA->id,
                'contact_user_id' => $other->id,
                'status' => 'accepted',
            ]);

            $conversations[$other->id] = Conversation::create([
                'user_one_id' => min($userA->id, $other->id),
                'user_two_id' => max($userA->id, $other->id),
            ]);
        }

        $this->travel(-2)->hours();
        Message::create([
            'conversation_id' => $conversations[$userB->id]->id,
            'sender_id' => $userA->id,
            'body' => 'Old message',
        ]);

        $this->travelBack();
        Message::create([
            'conversation_id' => $conversations[$userC->id]->id,
            'sender_id' => $userA->id,
            'body' => 'New message',
        ]);

        $response = $this->actingAs($userA)
            ->get(route('conversations.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['NewerContact', 'OlderContact']);
    }

    public function test_conversations_index_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('conversations.index'))
            ->assertOk();
    }

    public function test_expired_ignore_does_not_exclude_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        Ignore::create([
            'ignorer_id' => $userA->id,
            'ignored_id' => $userB->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($userA)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee($userB->name);
    }

    public function test_conversation_show_displays_messages_from_both_users(): void
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
            'body' => 'Hello from A',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userB->id,
            'body' => 'Hello from B',
        ]);

        $this->actingAs($userA)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertSee('Hello from A')
            ->assertSee('Hello from B');
    }
}
