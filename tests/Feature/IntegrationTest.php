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
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function sendMessage(User $sender, User $recipient, Conversation $conversation, string $body): void
    {
        Livewire::actingAs($sender)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $recipient,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', $body)
            ->call('sendMessage')
            ->assertHasNoErrors();
    }

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
        Event::fake([MessageSent::class]);

        $this->sendMessage($userA, $userB, $conversation, 'Hi!');
        $this->sendMessage($userB, $userA, $conversation, 'Hey!');

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

    public function test_ignore_then_block_cleans_everything(): void
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

        // Ignore first
        Ignore::create([
            'ignorer_id' => $userA->id,
            'ignored_id' => $userB->id,
            'expires_at' => now()->addDay(),
        ]);

        // Then block
        $this->actingAs($userA)
            ->post(route('blocks.store'), ['user_id' => $userB->id]);

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('ignores', 0);
        $this->assertDatabaseHas('blocks', [
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);
    }

    public function test_trash_then_restore_then_message(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Create accepted contact
        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        // Trash the contact
        $trash = Trash::create([
            'user_id' => $userA->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->addDays(7),
        ]);

        // Restore from trash
        $this->actingAs($userA)
            ->delete(route('trashes.destroy', $trash));

        $this->assertDatabaseCount('trashes', 0);

        // Send a message
        Event::fake([MessageSent::class]);

        $this->sendMessage($userA, $userB, $conversation, 'Hello again!');

        $this->assertDatabaseCount('messages', 1);

        $message = Message::first();
        $this->assertEquals($conversation->id, $message->conversation_id);
        $this->assertEquals($userA->id, $message->sender_id);
        $this->assertEquals('Hello again!', $message->body);
    }

    public function test_accept_request_then_ignore_then_cancel_ignore_then_message(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Send contact request
        $this->actingAs($userA)
            ->post(route('contacts.store'), ['email' => $userB->email]);

        $contact = Contact::first();

        // Accept
        $this->actingAs($userB)
            ->put(route('contacts.update', $contact), ['action' => 'accept']);

        $conversation = Conversation::first();
        $this->assertNotNull($conversation);

        // Ignore
        $this->actingAs($userA)
            ->post(route('ignores.store'), [
                'user_id' => $userB->id,
                'duration' => '24h',
            ]);

        $ignore = Ignore::first();
        $this->assertNotNull($ignore);

        // Cancel ignore
        $this->actingAs($userA)
            ->delete(route('ignores.destroy', $ignore));

        $this->assertDatabaseCount('ignores', 0);

        // Send message
        Event::fake([MessageSent::class]);

        $this->sendMessage($userA, $userB, $conversation, 'Hi after unignore!');

        $this->assertDatabaseCount('messages', 1);

        $message = Message::first();
        $this->assertEquals($conversation->id, $message->conversation_id);
        $this->assertEquals($userA->id, $message->sender_id);
        $this->assertEquals('Hi after unignore!', $message->body);
    }

    public function test_user_deletion_cascades_blocks_and_ignores(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Block::create([
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);

        Ignore::create([
            'ignorer_id' => $userA->id,
            'ignored_id' => $userB->id,
            'expires_at' => now()->addDay(),
        ]);

        $userA->delete();

        $this->assertDatabaseCount('blocks', 0);
        $this->assertDatabaseCount('ignores', 0);
    }

    public function test_two_way_messaging_lifecycle(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Create accepted contact and conversation
        Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        // Exchange messages back and forth
        Event::fake([MessageSent::class]);

        $this->sendMessage($userA, $userB, $conversation, 'Hey B!');
        $this->sendMessage($userB, $userA, $conversation, 'Hey A!');
        $this->sendMessage($userA, $userB, $conversation, 'How are you?');
        $this->sendMessage($userB, $userA, $conversation, 'Great, thanks!');

        $this->assertDatabaseCount('messages', 4);

        $messages = Message::orderBy('id')->get();

        $this->assertEquals($userA->id, $messages[0]->sender_id);
        $this->assertEquals('Hey B!', $messages[0]->body);

        $this->assertEquals($userB->id, $messages[1]->sender_id);
        $this->assertEquals('Hey A!', $messages[1]->body);

        $this->assertEquals($userA->id, $messages[2]->sender_id);
        $this->assertEquals('How are you?', $messages[2]->body);

        $this->assertEquals($userB->id, $messages[3]->sender_id);
        $this->assertEquals('Great, thanks!', $messages[3]->body);
    }
}
