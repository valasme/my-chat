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

class BlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_blocks(): void
    {
        $this->get(route('blocks.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_blocked_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('blocks.index'))
            ->assertOk();
    }

    public function test_user_can_block_accepted_contact(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($userA)
            ->post(route('blocks.store'), ['user_id' => $userB->id])
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseHas('blocks', [
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);
    }

    public function test_blocking_deletes_contact(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($userA)
            ->post(route('blocks.store'), ['user_id' => $userB->id]);

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_blocking_deletes_conversation_and_messages(): void
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
            'body' => 'Hello',
        ]);

        $this->actingAs($userA)
            ->post(route('blocks.store'), ['user_id' => $userB->id]);

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_blocking_deletes_ignores(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        Ignore::create([
            'ignorer_id' => $userA->id,
            'ignored_id' => $userB->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($userA)
            ->post(route('blocks.store'), ['user_id' => $userB->id]);

        $this->assertDatabaseCount('ignores', 0);
    }

    public function test_blocking_deletes_trash(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        Trash::create([
            'user_id' => $userA->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($userA)
            ->post(route('blocks.store'), ['user_id' => $userB->id]);

        $this->assertDatabaseCount('trashes', 0);
    }

    public function test_user_can_block_pending_contact(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $userB->id,
            'contact_user_id' => $userA->id,
            'status' => 'pending',
        ]);

        $this->actingAs($userA)
            ->post(route('blocks.store'), ['user_id' => $userB->id])
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseHas('blocks', [
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);
    }

    public function test_cannot_block_non_contact(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)
            ->post(route('blocks.store'), ['user_id' => $userB->id])
            ->assertSessionHasErrors('user_id');
    }

    public function test_user_can_unblock(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $block = Block::create([
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);

        $this->actingAs($userA)
            ->delete(route('blocks.destroy', $block))
            ->assertRedirect(route('blocks.index'));

        $this->assertDatabaseMissing('blocks', ['id' => $block->id]);
    }

    public function test_only_blocker_can_unblock(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $block = Block::create([
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);

        $this->actingAs($userB)
            ->delete(route('blocks.destroy', $block))
            ->assertForbidden();
    }

    public function test_blocked_user_cannot_send_contact_request(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Block::create([
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);

        $this->actingAs($userB)
            ->post(route('contacts.store'), ['email' => $userA->email])
            ->assertSessionHasErrors('email');
    }

    public function test_cannot_block_self(): void
    {
        $user = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $user->id,
            'contact_user_id' => $user->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($user)
            ->post(route('blocks.store'), ['user_id' => $user->id])
            ->assertSessionHasErrors('user_id');
    }

    public function test_cannot_block_already_blocked_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        Block::create([
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);

        $this->actingAs($userA)
            ->post(route('blocks.store'), ['user_id' => $userB->id])
            ->assertSessionHasErrors('user_id');
    }

    public function test_user_id_must_be_valid_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('blocks.store'), ['user_id' => 999999])
            ->assertSessionHasErrors('user_id');
    }

    public function test_user_id_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('blocks.store'), [])
            ->assertSessionHasErrors('user_id');
    }

    public function test_blocking_deletes_reverse_ignore(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        Ignore::create([
            'ignorer_id' => $userA->id,
            'ignored_id' => $userB->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($userB)
            ->post(route('blocks.store'), ['user_id' => $userA->id]);

        $this->assertDatabaseCount('ignores', 0);
    }

    public function test_block_list_sorts_az(): void
    {
        $user = User::factory()->create();
        $alice = User::factory()->create(['name' => 'Alice']);
        $zara = User::factory()->create(['name' => 'Zara']);

        Block::create(['blocker_id' => $user->id, 'blocked_id' => $zara->id]);
        Block::create(['blocker_id' => $user->id, 'blocked_id' => $alice->id]);

        $this->actingAs($user)
            ->get(route('blocks.index', ['sort' => 'az']))
            ->assertOk()
            ->assertSeeInOrder(['Alice', 'Zara']);
    }

    public function test_block_list_sorts_za(): void
    {
        $user = User::factory()->create();
        $alice = User::factory()->create(['name' => 'Alice']);
        $zara = User::factory()->create(['name' => 'Zara']);

        Block::create(['blocker_id' => $user->id, 'blocked_id' => $zara->id]);
        Block::create(['blocker_id' => $user->id, 'blocked_id' => $alice->id]);

        $this->actingAs($user)
            ->get(route('blocks.index', ['sort' => 'za']))
            ->assertOk()
            ->assertSeeInOrder(['Zara', 'Alice']);
    }

    public function test_blocker_who_blocked_user_cannot_send_contact_request(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Block::create([
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);

        $this->actingAs($userA)
            ->post(route('contacts.store'), ['email' => $userB->email])
            ->assertSessionHasErrors('email');
    }

    public function test_guests_cannot_store_blocks(): void
    {
        $user = User::factory()->create();

        $this->post(route('blocks.store'), ['user_id' => $user->id])
            ->assertRedirect(route('login'));
    }

    public function test_guests_cannot_delete_blocks(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $block = Block::create([
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);

        $this->delete(route('blocks.destroy', $block))
            ->assertRedirect(route('login'));
    }
}
