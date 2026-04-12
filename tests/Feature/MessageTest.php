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

class MessageTest extends TestCase
{
    use RefreshDatabase;

    private function createContactAndConversation(): array
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

        return [$userA, $userB, $contact, $conversation];
    }

    public function test_user_can_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $this->actingAs($userA)
            ->post(route('messages.store', $conversation), ['body' => 'Hello!'])
            ->assertRedirect(route('conversations.show', $conversation));

        $this->assertDatabaseCount('messages', 1);

        $message = Message::first();
        $this->assertEquals($userA->id, $message->sender_id);
        $this->assertEquals($conversation->id, $message->conversation_id);
        $this->assertEquals('Hello!', $message->body);
    }

    public function test_message_body_is_encrypted_in_database(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $this->actingAs($userA)
            ->post(route('messages.store', $conversation), ['body' => 'Secret message']);

        $rawBody = \DB::table('messages')->first()->body;
        $this->assertNotEquals('Secret message', $rawBody);

        $message = Message::first();
        $this->assertEquals('Secret message', $message->body);
    }

    public function test_non_participant_cannot_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('messages.store', $conversation), ['body' => 'Hello!'])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_message_requires_body(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $this->actingAs($userA)
            ->post(route('messages.store', $conversation), ['body' => ''])
            ->assertSessionHasErrors('body');
    }

    public function test_message_body_max_length(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $this->actingAs($userA)
            ->post(route('messages.store', $conversation), ['body' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('body');
    }

    public function test_conversation_shows_messages(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userA->id,
            'body' => 'Hello friend!',
        ]);

        $this->actingAs($userA)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertSee('Hello friend!');
    }

    public function test_guests_cannot_send_messages(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $this->post(route('messages.store', $conversation), ['body' => 'Hello!'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_blocked_user_cannot_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Block::create([
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);

        $this->actingAs($userB)
            ->post(route('messages.store', $conversation), ['body' => 'Hello!'])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_blocker_cannot_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Block::create([
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);

        $this->actingAs($userA)
            ->post(route('messages.store', $conversation), ['body' => 'Hello!'])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_ignored_user_cannot_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Ignore::create([
            'ignorer_id' => $userA->id,
            'ignored_id' => $userB->id,
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($userB)
            ->post(route('messages.store', $conversation), ['body' => 'Hello!']);

        $response->assertSessionHasErrors('body');

        $errors = session('errors')->get('body');
        $this->assertTrue(
            collect($errors)->contains(fn ($msg) => str_contains($msg, 'unavailable until')),
        );

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_message_at_max_length_succeeds(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $this->actingAs($userA)
            ->post(route('messages.store', $conversation), ['body' => str_repeat('a', 5000)])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('messages', 1);
    }

    public function test_trashed_contact_cannot_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Trash::create([
            'user_id' => $userA->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($userA)
            ->post(route('messages.store', $conversation), ['body' => 'Hello!'])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_both_participants_can_send_messages(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $this->actingAs($userA)
            ->post(route('messages.store', $conversation), ['body' => 'Hello from A!'])
            ->assertSessionDoesntHaveErrors();

        $this->actingAs($userB)
            ->post(route('messages.store', $conversation), ['body' => 'Hello from B!'])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('messages', 2);

        $messages = Message::where('conversation_id', $conversation->id)->get();
        $this->assertTrue($messages->contains(fn ($m) => $m->sender_id === $userA->id && $m->body === 'Hello from A!'));
        $this->assertTrue($messages->contains(fn ($m) => $m->sender_id === $userB->id && $m->body === 'Hello from B!'));
    }

    public function test_message_body_cannot_be_null(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $this->actingAs($userA)
            ->post(route('messages.store', $conversation), ['body' => null])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_multiple_messages_stored_in_order(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        foreach (['First', 'Second', 'Third'] as $body) {
            $this->actingAs($userA)
                ->post(route('messages.store', $conversation), ['body' => $body]);
        }

        $this->assertDatabaseCount('messages', 3);

        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('body')
            ->toArray();

        $this->assertEquals(['First', 'Second', 'Third'], $messages);
    }

    public function test_message_requires_existing_conversation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('messages.store', ['conversation' => 999999]), ['body' => 'Hello!'])
            ->assertNotFound();
    }
}
