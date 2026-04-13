<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_contacts(): void
    {
        $this->get(route('contacts.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_contacts_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('contacts.index'))
            ->assertOk();
    }

    public function test_contacts_index_sorts_az(): void
    {
        $user = User::factory()->create();
        $alice = User::factory()->create(['name' => 'Alice']);
        $zara = User::factory()->create(['name' => 'Zara']);

        Contact::factory()->create([
            'user_id' => $user->id,
            'contact_user_id' => $zara->id,
            'status' => 'accepted',
        ]);

        Contact::factory()->create([
            'user_id' => $user->id,
            'contact_user_id' => $alice->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($user)
            ->get(route('contacts.index', ['sort' => 'az']));

        $response->assertOk();
        $response->assertSeeInOrder(['Alice', 'Zara']);
    }

    public function test_contacts_index_sorts_za(): void
    {
        $user = User::factory()->create();
        $alice = User::factory()->create(['name' => 'Alice']);
        $zara = User::factory()->create(['name' => 'Zara']);

        Contact::factory()->create([
            'user_id' => $user->id,
            'contact_user_id' => $zara->id,
            'status' => 'accepted',
        ]);

        Contact::factory()->create([
            'user_id' => $user->id,
            'contact_user_id' => $alice->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($user)
            ->get(route('contacts.index', ['sort' => 'za']));

        $response->assertOk();
        $response->assertSeeInOrder(['Zara', 'Alice']);
    }

    public function test_user_can_view_create_contact_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('contacts.create'))
            ->assertOk();
    }

    public function test_user_can_send_contact_request(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $this->actingAs($sender)
            ->post(route('contacts.store'), ['email' => $recipient->email])
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseHas('contacts', [
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
            'status' => 'pending',
        ]);
    }

    public function test_user_cannot_send_contact_request_to_self(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('contacts.store'), ['email' => $user->email])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('contacts', [
            'user_id' => $user->id,
            'contact_user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_send_duplicate_contact_request(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($sender)
            ->post(route('contacts.store'), ['email' => $recipient->email])
            ->assertSessionHasErrors('email');
    }

    public function test_user_cannot_send_request_if_reverse_exists(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $recipient->id,
            'contact_user_id' => $sender->id,
            'status' => 'pending',
        ]);

        $this->actingAs($sender)
            ->post(route('contacts.store'), ['email' => $recipient->email])
            ->assertSessionHasErrors('email');
    }

    public function test_recipient_can_accept_contact_request(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($recipient)
            ->put(route('contacts.update', $contact), ['action' => 'accept'])
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'status' => 'accepted',
        ]);
    }

    public function test_accepting_contact_creates_conversation(): void
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

    public function test_recipient_can_decline_contact_request(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($recipient)
            ->put(route('contacts.update', $contact), ['action' => 'decline'])
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseMissing('contacts', [
            'id' => $contact->id,
        ]);
    }

    public function test_sender_cannot_accept_own_request(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($sender)
            ->put(route('contacts.update', $contact), ['action' => 'accept'])
            ->assertForbidden();
    }

    public function test_user_can_delete_accepted_contact(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($userA)
            ->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseMissing('contacts', [
            'id' => $contact->id,
        ]);
    }

    public function test_either_side_can_delete_contact(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($userB)
            ->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseMissing('contacts', [
            'id' => $contact->id,
        ]);
    }

    public function test_user_cannot_view_other_users_contact(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $outsider = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($outsider)
            ->get(route('contacts.show', $contact))
            ->assertForbidden();
    }

    public function test_user_can_view_own_contact_detail(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($userA)
            ->get(route('contacts.show', $contact))
            ->assertOk()
            ->assertSee($userB->name);
    }

    public function test_user_cannot_request_nonexistent_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('contacts.store'), ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_contacts_index_shows_accepted_contacts(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($userA)
            ->get(route('contacts.index'))
            ->assertOk()
            ->assertSee($userB->name);
    }

    public function test_contacts_index_shows_incoming_requests(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($recipient)
            ->get(route('contacts.index'))
            ->assertOk()
            ->assertSee($sender->name);
    }

    public function test_sender_can_cancel_pending_request(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($sender)
            ->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseMissing('contacts', [
            'id' => $contact->id,
        ]);
    }

    public function test_guests_cannot_store_contacts(): void
    {
        $this->post(route('contacts.store'), ['email' => 'test@example.com'])
            ->assertRedirect(route('login'));
    }

    public function test_guests_cannot_update_contacts(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->put(route('contacts.update', $contact), ['action' => 'accept'])
            ->assertRedirect(route('login'));
    }

    public function test_guests_cannot_delete_contacts(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $this->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('login'));
    }

    public function test_contact_request_requires_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('contacts.store'), [])
            ->assertSessionHasErrors('email');
    }

    public function test_contact_request_requires_valid_email_format(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('contacts.store'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_third_party_cannot_accept_contact(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $outsider = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($outsider)
            ->put(route('contacts.update', $contact), ['action' => 'accept'])
            ->assertForbidden();
    }

    public function test_cannot_accept_already_accepted_contact(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($recipient)
            ->put(route('contacts.update', $contact), ['action' => 'accept'])
            ->assertForbidden();
    }

    public function test_deleting_contact_deletes_conversation_and_messages(): void
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

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userB->id,
            'body' => 'Hi there',
        ]);

        $this->actingAs($userA)
            ->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_contacts_index_shows_outgoing_requests(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($sender)
            ->get(route('contacts.index'))
            ->assertOk()
            ->assertSee($recipient->name);
    }

    public function test_user_can_view_contact_from_other_side(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($userB)
            ->get(route('contacts.show', $contact))
            ->assertOk()
            ->assertSee($userA->name);
    }

    public function test_update_with_invalid_action(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
            'status' => 'pending',
        ]);

        $this->actingAs($recipient)
            ->put(route('contacts.update', $contact), ['action' => 'invalid'])
            ->assertSessionHasErrors('action');
    }
}
