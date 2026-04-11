<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Trash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_trash(): void
    {
        $this->get(route('trashes.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_trash_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('trashes.index'))
            ->assertOk();
    }

    public function test_user_can_trash_accepted_contact(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($userA)
            ->post(route('trashes.store'), [
                'contact_id' => $contact->id,
                'duration' => '7d',
            ])
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseHas('trashes', [
            'user_id' => $userA->id,
            'contact_id' => $contact->id,
        ]);
    }

    public function test_quick_delete_erases_messages(): void
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
            'body' => 'Secret message',
        ]);

        $this->actingAs($userA)
            ->post(route('trashes.store'), [
                'contact_id' => $contact->id,
                'is_quick_delete' => '1',
            ])
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseHas('trashes', [
            'user_id' => $userA->id,
            'is_quick_delete' => true,
        ]);
    }

    public function test_user_can_restore_from_trash(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $trash = Trash::create([
            'user_id' => $userA->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($userA)
            ->delete(route('trashes.destroy', $trash))
            ->assertRedirect(route('trashes.index'));

        $this->assertDatabaseMissing('trashes', ['id' => $trash->id]);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    }

    public function test_force_delete_removes_contact_and_conversation(): void
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
            'body' => 'Test',
        ]);

        $trash = Trash::create([
            'user_id' => $userA->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($userA)
            ->delete(route('trashes.force-delete', $trash))
            ->assertRedirect(route('trashes.index'));

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseMissing('trashes', ['id' => $trash->id]);
    }

    public function test_only_owner_can_restore_trash(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $trash = Trash::create([
            'user_id' => $userA->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($userB)
            ->delete(route('trashes.destroy', $trash))
            ->assertForbidden();
    }

    public function test_clean_expired_trashes_command(): void
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

        Trash::create([
            'user_id' => $userA->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('app:clean-expired-trashes')
            ->assertSuccessful();

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseCount('trashes', 0);
    }

    public function test_cannot_trash_non_accepted_contact(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'pending',
        ]);

        $this->actingAs($userA)
            ->post(route('trashes.store'), [
                'contact_id' => $contact->id,
                'duration' => '7d',
            ])
            ->assertSessionHasErrors('contact_id');
    }

    public function test_force_delete_handles_missing_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        // No conversation exists for this contact
        $trash = Trash::create([
            'user_id' => $userA->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($userA)
            ->delete(route('trashes.force-delete', $trash))
            ->assertRedirect(route('trashes.index'));

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseMissing('trashes', ['id' => $trash->id]);
    }
}
