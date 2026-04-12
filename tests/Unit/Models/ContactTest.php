<?php

namespace Tests\Unit\Models;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    // ── Scopes ──

    public function test_scope_pending_filters_pending_contacts(): void
    {
        $pending = Contact::factory()->pending()->create();
        Contact::factory()->accepted()->create();

        $results = Contact::pending()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($pending));
    }

    public function test_scope_accepted_filters_accepted_contacts(): void
    {
        Contact::factory()->pending()->create();
        $accepted = Contact::factory()->accepted()->create();

        $results = Contact::accepted()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($accepted));
    }

    public function test_scope_for_user_returns_contacts_in_both_directions(): void
    {
        $user = User::factory()->create();
        $other1 = User::factory()->create();
        $other2 = User::factory()->create();

        $sent = Contact::factory()->create([
            'user_id' => $user->id,
            'contact_user_id' => $other1->id,
        ]);
        $received = Contact::factory()->create([
            'user_id' => $other2->id,
            'contact_user_id' => $user->id,
        ]);
        // Unrelated contact
        Contact::factory()->create();

        $results = Contact::forUser($user->id)->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains($sent));
        $this->assertTrue($results->contains($received));
    }

    public function test_scope_incoming_returns_contacts_where_user_is_recipient(): void
    {
        $user = User::factory()->create();
        $sender = User::factory()->create();

        $incoming = Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $user->id,
        ]);
        Contact::factory()->create([
            'user_id' => $user->id,
            'contact_user_id' => $sender->id,
        ]);

        $results = Contact::incoming($user->id)->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($incoming));
    }

    public function test_scope_outgoing_returns_contacts_where_user_is_sender(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $outgoing = Contact::factory()->create([
            'user_id' => $user->id,
            'contact_user_id' => $other->id,
        ]);
        Contact::factory()->create([
            'user_id' => $other->id,
            'contact_user_id' => $user->id,
        ]);

        $results = Contact::outgoing($user->id)->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($outgoing));
    }

    public function test_scope_between_finds_contact_in_either_direction(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
        ]);

        // A→B direction
        $this->assertCount(1, Contact::between($userA->id, $userB->id)->get());
        // B→A direction (reversed args)
        $this->assertCount(1, Contact::between($userB->id, $userA->id)->get());
    }

    public function test_scope_between_returns_empty_when_no_contact(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->assertCount(0, Contact::between($userA->id, $userB->id)->get());
    }

    public function test_scope_between_does_not_return_unrelated_contacts(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userC->id,
        ]);

        $this->assertCount(0, Contact::between($userA->id, $userB->id)->get());
    }

    // ── getOtherUser ──

    public function test_get_other_user_returns_contact_user_when_given_sender_id(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
        ]);

        $other = $contact->getOtherUser($sender->id);

        $this->assertTrue($other->is($recipient));
    }

    public function test_get_other_user_returns_sender_when_given_recipient_id(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
        ]);

        $other = $contact->getOtherUser($recipient->id);

        $this->assertTrue($other->is($sender));
    }

    // ── involvesUser ──

    public function test_involves_user_returns_true_for_sender(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
        ]);

        $this->assertTrue($contact->involvesUser($sender->id));
    }

    public function test_involves_user_returns_true_for_recipient(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $sender->id,
            'contact_user_id' => $recipient->id,
        ]);

        $this->assertTrue($contact->involvesUser($recipient->id));
    }

    public function test_involves_user_returns_false_for_unrelated_user(): void
    {
        $contact = Contact::factory()->create();
        $stranger = User::factory()->create();

        $this->assertFalse($contact->involvesUser($stranger->id));
    }

    // ── Relationships ──

    public function test_user_relationship_returns_sender(): void
    {
        $sender = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $sender->id]);

        $this->assertTrue($contact->user->is($sender));
    }

    public function test_contact_user_relationship_returns_recipient(): void
    {
        $recipient = User::factory()->create();
        $contact = Contact::factory()->create(['contact_user_id' => $recipient->id]);

        $this->assertTrue($contact->contactUser->is($recipient));
    }
}
