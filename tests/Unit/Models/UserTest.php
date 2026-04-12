<?php

namespace Tests\Unit\Models;

use App\Models\Block;
use App\Models\Contact;
use App\Models\Ignore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    // ── Initials ──

    public function test_initials_returns_first_letters_of_two_words(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);

        $this->assertSame('JD', $user->initials());
    }

    public function test_initials_returns_single_letter_for_single_word_name(): void
    {
        $user = User::factory()->create(['name' => 'Alice']);

        $this->assertSame('A', $user->initials());
    }

    public function test_initials_takes_only_first_two_words(): void
    {
        $user = User::factory()->create(['name' => 'Jane Marie Smith']);

        $this->assertSame('JM', $user->initials());
    }

    // ── Contact Relationships ──

    public function test_sent_contact_requests_returns_contacts_where_user_is_sender(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $user->id,
            'contact_user_id' => $other->id,
        ]);

        $sent = $user->sentContactRequests;

        $this->assertCount(1, $sent);
        $this->assertTrue($sent->first()->is($contact));
    }

    public function test_sent_contact_requests_excludes_received(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $other->id,
            'contact_user_id' => $user->id,
        ]);

        $this->assertCount(0, $user->sentContactRequests);
    }

    public function test_received_contact_requests_returns_contacts_where_user_is_recipient(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $contact = Contact::factory()->create([
            'user_id' => $other->id,
            'contact_user_id' => $user->id,
        ]);

        $received = $user->receivedContactRequests;

        $this->assertCount(1, $received);
        $this->assertTrue($received->first()->is($contact));
    }

    public function test_contacts_returns_only_accepted_contacts_in_both_directions(): void
    {
        $user = User::factory()->create();
        $friend1 = User::factory()->create();
        $friend2 = User::factory()->create();
        $pending = User::factory()->create();

        Contact::factory()->accepted()->create([
            'user_id' => $user->id,
            'contact_user_id' => $friend1->id,
        ]);
        Contact::factory()->accepted()->create([
            'user_id' => $friend2->id,
            'contact_user_id' => $user->id,
        ]);
        Contact::factory()->pending()->create([
            'user_id' => $user->id,
            'contact_user_id' => $pending->id,
        ]);

        $contacts = $user->contacts();

        $this->assertCount(2, $contacts);
    }

    // ── Block Relationships ──

    public function test_blocks_returns_users_blocked_by_this_user(): void
    {
        $user = User::factory()->create();
        $blocked = User::factory()->create();

        Block::factory()->create([
            'blocker_id' => $user->id,
            'blocked_id' => $blocked->id,
        ]);

        $this->assertCount(1, $user->blocks);
        $this->assertSame($blocked->id, $user->blocks->first()->blocked_id);
    }

    public function test_blocked_by_returns_users_who_blocked_this_user(): void
    {
        $user = User::factory()->create();
        $blocker = User::factory()->create();

        Block::factory()->create([
            'blocker_id' => $blocker->id,
            'blocked_id' => $user->id,
        ]);

        $this->assertCount(1, $user->blockedBy);
        $this->assertSame($blocker->id, $user->blockedBy->first()->blocker_id);
    }

    // ── Ignore Relationships ──

    public function test_ignores_returns_users_ignored_by_this_user(): void
    {
        $user = User::factory()->create();
        $ignored = User::factory()->create();

        Ignore::factory()->create([
            'ignorer_id' => $user->id,
            'ignored_id' => $ignored->id,
        ]);

        $this->assertCount(1, $user->ignores);
        $this->assertSame($ignored->id, $user->ignores->first()->ignored_id);
    }

    public function test_ignored_by_returns_users_who_ignored_this_user(): void
    {
        $user = User::factory()->create();
        $ignorer = User::factory()->create();

        Ignore::factory()->create([
            'ignorer_id' => $ignorer->id,
            'ignored_id' => $user->id,
        ]);

        $this->assertCount(1, $user->ignoredBy);
        $this->assertSame($ignorer->id, $user->ignoredBy->first()->ignorer_id);
    }

    // ── Helper Methods ──

    public function test_is_contact_of_returns_true_for_accepted_contact(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Contact::factory()->accepted()->create([
            'user_id' => $user->id,
            'contact_user_id' => $other->id,
        ]);

        $this->assertTrue($user->isContactOf($other));
    }

    public function test_is_contact_of_returns_false_for_pending_contact(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Contact::factory()->pending()->create([
            'user_id' => $user->id,
            'contact_user_id' => $other->id,
        ]);

        $this->assertFalse($user->isContactOf($other));
    }

    public function test_is_contact_of_returns_true_when_other_user_sent_request(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Contact::factory()->accepted()->create([
            'user_id' => $other->id,
            'contact_user_id' => $user->id,
        ]);

        $this->assertTrue($user->isContactOf($other));
    }

    public function test_is_contact_of_returns_false_when_no_contact_exists(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertFalse($user->isContactOf($other));
    }

    public function test_has_pending_contact_with_returns_true_for_pending(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Contact::factory()->pending()->create([
            'user_id' => $user->id,
            'contact_user_id' => $other->id,
        ]);

        $this->assertTrue($user->hasPendingContactWith($other));
    }

    public function test_has_pending_contact_with_returns_false_for_accepted(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Contact::factory()->accepted()->create([
            'user_id' => $user->id,
            'contact_user_id' => $other->id,
        ]);

        $this->assertFalse($user->hasPendingContactWith($other));
    }

    public function test_has_any_contact_with_returns_true_for_any_status(): void
    {
        $user = User::factory()->create();
        $pending = User::factory()->create();
        $accepted = User::factory()->create();

        Contact::factory()->pending()->create([
            'user_id' => $user->id,
            'contact_user_id' => $pending->id,
        ]);
        Contact::factory()->accepted()->create([
            'user_id' => $user->id,
            'contact_user_id' => $accepted->id,
        ]);

        $this->assertTrue($user->hasAnyContactWith($pending));
        $this->assertTrue($user->hasAnyContactWith($accepted));
    }

    public function test_has_any_contact_with_returns_false_when_no_contact(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $this->assertFalse($user->hasAnyContactWith($stranger));
    }

    public function test_has_blocked_user_returns_true_when_blocked(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Block::factory()->create([
            'blocker_id' => $user->id,
            'blocked_id' => $other->id,
        ]);

        $this->assertTrue($user->hasBlockedUser($other));
    }

    public function test_has_blocked_user_returns_false_when_not_blocked(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertFalse($user->hasBlockedUser($other));
    }

    public function test_has_blocked_user_returns_false_when_blocked_in_reverse(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Block::factory()->create([
            'blocker_id' => $other->id,
            'blocked_id' => $user->id,
        ]);

        $this->assertFalse($user->hasBlockedUser($other));
    }

    public function test_is_blocked_by_user_returns_true_when_other_blocked_this(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Block::factory()->create([
            'blocker_id' => $other->id,
            'blocked_id' => $user->id,
        ]);

        $this->assertTrue($user->isBlockedByUser($other));
    }

    public function test_is_blocked_by_user_returns_false_when_not_blocked(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertFalse($user->isBlockedByUser($other));
    }

    public function test_is_ignoring_user_returns_true_when_active_ignore(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Ignore::factory()->create([
            'ignorer_id' => $user->id,
            'ignored_id' => $other->id,
            'expires_at' => now()->addHour(),
        ]);

        $this->assertTrue($user->isIgnoringUser($other));
    }

    public function test_is_ignoring_user_returns_false_when_expired(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Ignore::factory()->create([
            'ignorer_id' => $user->id,
            'ignored_id' => $other->id,
            'expires_at' => now()->subHour(),
        ]);

        $this->assertFalse($user->isIgnoringUser($other));
    }

    public function test_is_ignoring_user_returns_false_when_no_ignore(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertFalse($user->isIgnoringUser($other));
    }

    public function test_is_ignored_by_user_delegates_to_other_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Ignore::factory()->create([
            'ignorer_id' => $other->id,
            'ignored_id' => $user->id,
            'expires_at' => now()->addHour(),
        ]);

        $this->assertTrue($user->isIgnoredByUser($other));
    }

    public function test_is_ignored_by_user_returns_false_when_not_ignored(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertFalse($user->isIgnoredByUser($other));
    }
}
