<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageSentEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_implements_should_broadcast(): void
    {
        $message = $this->createMessage();

        $event = new MessageSent($message);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_event_broadcasts_on_correct_private_channel(): void
    {
        $message = $this->createMessage();

        $event = new MessageSent($message);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-conversation.'.$message->conversation_id, $channels[0]->name);
    }

    public function test_event_has_correct_broadcast_name(): void
    {
        $message = $this->createMessage();

        $event = new MessageSent($message);

        $this->assertEquals('MessageSent', $event->broadcastAs());
    }

    public function test_event_payload_contains_required_fields(): void
    {
        $message = $this->createMessage();
        $message->load('sender');

        $event = new MessageSent($message);
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('conversation_id', $payload);
        $this->assertArrayHasKey('sender_id', $payload);
        $this->assertArrayHasKey('sender_name', $payload);
        $this->assertArrayHasKey('body', $payload);
        $this->assertArrayHasKey('created_at', $payload);

        $this->assertEquals($message->id, $payload['id']);
        $this->assertEquals($message->conversation_id, $payload['conversation_id']);
        $this->assertEquals($message->sender_id, $payload['sender_id']);
        $this->assertEquals($message->sender->name, $payload['sender_name']);
        $this->assertEquals($message->body, $payload['body']);
    }

    public function test_event_payload_contains_decrypted_body(): void
    {
        $message = $this->createMessage();
        $message->load('sender');

        $event = new MessageSent($message);
        $payload = $event->broadcastWith();

        $this->assertEquals('Hello, world!', $payload['body']);
    }

    private function createMessage(): Message
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        return Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userA->id,
            'body' => 'Hello, world!',
        ]);
    }
}
