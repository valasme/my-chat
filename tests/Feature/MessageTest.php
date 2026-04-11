<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
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
}
