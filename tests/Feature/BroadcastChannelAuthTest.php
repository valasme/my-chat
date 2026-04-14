<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_authorize_on_conversation_channel(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        $this->actingAs($userA)
            ->post('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => 'private-conversation.'.$conversation->id,
            ])
            ->assertOk();
    }

    public function test_other_participant_can_authorize_on_conversation_channel(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        $this->actingAs($userB)
            ->post('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => 'private-conversation.'.$conversation->id,
            ])
            ->assertOk();
    }

    public function test_non_participant_cannot_authorize_on_conversation_channel(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $outsider = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        $this->actingAs($outsider)
            ->post('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => 'private-conversation.'.$conversation->id,
            ])
            ->assertForbidden();
    }

    public function test_guest_cannot_authorize_on_conversation_channel(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        $this->post('/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => 'private-conversation.'.$conversation->id,
        ])->assertForbidden();
    }

    public function test_nonexistent_conversation_denies_authorization(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => 'private-conversation.999999',
            ])
            ->assertForbidden();
    }
}
