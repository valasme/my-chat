<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Livewire\ConversationShow;
use App\Models\Block;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Ignore;
use App\Models\Message;
use App\Models\Trash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class ConversationShowLivewireTest extends TestCase
{
    use RefreshDatabase;

    private function createContactAndConversation(): array
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->accepted()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
        ]);

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        return [$userA, $userB, $contact, $conversation];
    }

    public function test_component_renders_with_messages(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

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

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->assertSee('Hello from A')
            ->assertSee('Hello from B')
            ->assertSee($userB->name);
    }

    public function test_component_renders_empty_state(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->assertSee('No messages yet');
    }

    public function test_component_shows_ignored_bottom_bar(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $ignore = Ignore::create([
            'ignorer_id' => $userB->id,
            'ignored_id' => $userA->id,
            'expires_at' => now()->addDay(),
        ]);

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => $ignore,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->assertSee('unavailable until')
            ->assertSee('Go back')
            ->assertDontSee('Type a message...');
    }

    public function test_component_shows_trashed_callout(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => true,
                'isBlocked' => false,
            ])
            ->assertSee('Restore to see new messages');
    }

    public function test_component_hides_input_when_trashed(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => true,
                'isBlocked' => false,
            ])
            ->assertDontSee('Type a message...');
    }

    public function test_component_hides_input_when_ignored(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $ignore = Ignore::create([
            'ignorer_id' => $userB->id,
            'ignored_id' => $userA->id,
            'expires_at' => now()->addDay(),
        ]);

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => $ignore,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->assertDontSee('Type a message...');
    }

    public function test_user_can_send_message(): void
    {
        Event::fake([MessageSent::class]);

        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $userA->id,
        ]);

        $message = Message::where('conversation_id', $conversation->id)->first();
        $this->assertEquals('Hello!', $message->body);
    }

    public function test_send_message_clears_body(): void
    {
        Event::fake([MessageSent::class]);

        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage')
            ->assertSet('body', '');
    }

    public function test_send_message_dispatches_broadcast_event(): void
    {
        Event::fake([MessageSent::class]);

        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage');

        Event::assertDispatched(MessageSent::class, function ($event) use ($conversation) {
            return $event->message->conversation_id === $conversation->id;
        });
    }

    public function test_send_message_requires_body(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', '')
            ->call('sendMessage')
            ->assertHasErrors(['body' => 'required']);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_send_message_body_max_length(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', str_repeat('a', 5001))
            ->call('sendMessage')
            ->assertHasErrors(['body' => 'max']);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_send_message_at_max_length_succeeds(): void
    {
        Event::fake([MessageSent::class]);

        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', str_repeat('a', 5000))
            ->call('sendMessage')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('messages', 1);
    }

    public function test_blocked_user_cannot_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Block::create([
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);

        Livewire::actingAs($userB)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userA,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => true,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage')
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_blocker_cannot_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Block::create([
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => true,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage')
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_ignored_user_cannot_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $ignore = Ignore::create([
            'ignorer_id' => $userA->id,
            'ignored_id' => $userB->id,
            'expires_at' => now()->addDay(),
        ]);

        Livewire::actingAs($userB)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userA,
                'isIgnored' => $ignore,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage')
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_trashed_contact_cannot_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Trash::create([
            'user_id' => $userA->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->addDays(7),
        ]);

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => true,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage')
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_both_participants_can_send_messages(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Event::fake([MessageSent::class]);

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello from A!')
            ->call('sendMessage')
            ->assertHasNoErrors();

        Livewire::actingAs($userB)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userA,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello from B!')
            ->call('sendMessage')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('messages', 2);
    }

    public function test_message_is_encrypted_in_database(): void
    {
        Event::fake([MessageSent::class]);

        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Secret message')
            ->call('sendMessage');

        $rawBody = \DB::table('messages')->first()->body;
        $this->assertNotEquals('Secret message', $rawBody);

        $message = Message::first();
        $this->assertEquals('Secret message', $message->body);
    }

    public function test_non_participant_cannot_send_message(): void
    {
        Event::fake([MessageSent::class]);

        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();
        $outsider = User::factory()->create();

        Livewire::actingAs($outsider)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userA,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage')
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_on_message_received_triggers_rerender(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userB->id,
            'body' => 'Real-time message',
        ]);

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->call('onMessageReceived', [
                'id' => $message->id,
                'conversation_id' => $conversation->id,
                'sender_id' => $userB->id,
                'sender_name' => $userB->name,
                'body' => 'Real-time message',
                'created_at' => $message->created_at->toIso8601String(),
            ])
            ->assertSee('Real-time message')
            ->assertDispatched('scroll-to-bottom');
    }

    public function test_on_message_received_ignores_own_messages(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->call('onMessageReceived', [
                'id' => 1,
                'conversation_id' => $conversation->id,
                'sender_id' => $userA->id,
                'sender_name' => $userA->name,
                'body' => 'My own message',
                'created_at' => now()->toIso8601String(),
            ])
            ->assertNotDispatched('scroll-to-bottom');
    }

    public function test_on_message_received_ignores_malformed_event(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->call('onMessageReceived', ['invalid' => 'data'])
            ->assertNotDispatched('scroll-to-bottom');
    }

    public function test_on_message_received_ignores_wrong_conversation(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->call('onMessageReceived', [
                'id' => 1,
                'conversation_id' => 99999,
                'sender_id' => $userB->id,
                'sender_name' => $userB->name,
                'body' => 'Wrong convo',
                'created_at' => now()->toIso8601String(),
            ])
            ->assertNotDispatched('scroll-to-bottom');
    }

    public function test_whitespace_only_message_is_rejected(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', '   ')
            ->call('sendMessage')
            ->assertHasErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_message_body_is_trimmed_before_storage(): void
    {
        Event::fake([MessageSent::class]);

        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', '  Hello!  ')
            ->call('sendMessage')
            ->assertHasNoErrors();

        $message = Message::first();
        $this->assertEquals('Hello!', $message->body);
    }

    public function test_send_message_is_rate_limited(): void
    {
        Event::fake([MessageSent::class]);

        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $rateLimitKey = 'chat-message:'.$userA->id;
        for ($i = 0; $i < 60; $i++) {
            RateLimiter::hit($rateLimitKey, 60);
        }

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage')
            ->assertHasErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_blocked_user_can_see_message_history(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userA->id,
            'body' => 'Message before block',
        ]);

        Block::create([
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);

        Livewire::actingAs($userB)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userA,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => true,
            ])
            ->assertSee('Message before block');
    }

    public function test_xss_is_escaped_in_message_display(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userA->id,
            'body' => '<script>alert("xss")</script>',
        ]);

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->assertDontSeeHtml('<script>alert("xss")</script>')
            ->assertSeeHtml('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;');
    }

    public function test_echo_listener_is_registered_for_conversation_channel(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $component = Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ]);

        $listeners = $component->instance()->getListeners();

        $expectedKey = "echo-private:conversation.{$conversation->id},MessageSent";
        $this->assertArrayHasKey($expectedKey, $listeners);
        $this->assertEquals('onMessageReceived', $listeners[$expectedKey]);
    }
}
