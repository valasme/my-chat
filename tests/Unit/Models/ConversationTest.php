<?php

namespace Tests\Unit\Models;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Ignore;
use App\Models\Message;
use App\Models\Trash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    // ── Relationships ──

    public function test_messages_returns_related_messages(): void
    {
        $conversation = Conversation::factory()->create();

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
        ]);
        // Unrelated message
        Message::factory()->create();

        $messages = $conversation->messages;

        $this->assertCount(1, $messages);
        $this->assertTrue($messages->first()->is($message));
    }

    public function test_user_one_relationship(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_one_id' => $user->id]);

        $this->assertTrue($conversation->userOne->is($user));
    }

    public function test_user_two_relationship(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_two_id' => $user->id]);

        $this->assertTrue($conversation->userTwo->is($user));
    }

    // ── scopeForUser ──

    public function test_scope_for_user_returns_conversations_as_user_one(): void
    {
        $user = User::factory()->create();

        $conversation = Conversation::factory()->create(['user_one_id' => $user->id]);
        Conversation::factory()->create(); // unrelated

        $results = Conversation::forUser($user->id)->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($conversation));
    }

    public function test_scope_for_user_returns_conversations_as_user_two(): void
    {
        $user = User::factory()->create();

        $conversation = Conversation::factory()->create(['user_two_id' => $user->id]);
        Conversation::factory()->create(); // unrelated

        $results = Conversation::forUser($user->id)->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($conversation));
    }

    public function test_scope_for_user_returns_both_directions(): void
    {
        $user = User::factory()->create();

        Conversation::factory()->create(['user_one_id' => $user->id]);
        Conversation::factory()->create(['user_two_id' => $user->id]);
        Conversation::factory()->create(); // unrelated

        $this->assertCount(2, Conversation::forUser($user->id)->get());
    }

    // ── scopeBetweenUsers ──

    public function test_scope_between_users_normalizes_ids_with_lower_first(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $lower = min($userA->id, $userB->id);
        $upper = max($userA->id, $userB->id);

        $conversation = Conversation::factory()->create([
            'user_one_id' => $lower,
            'user_two_id' => $upper,
        ]);

        // Both orderings should find the same conversation
        $resultAB = Conversation::betweenUsers($userA->id, $userB->id)->first();
        $resultBA = Conversation::betweenUsers($userB->id, $userA->id)->first();

        $this->assertNotNull($resultAB);
        $this->assertNotNull($resultBA);
        $this->assertTrue($resultAB->is($conversation));
        $this->assertTrue($resultBA->is($conversation));
    }

    public function test_scope_between_users_returns_empty_when_no_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->assertCount(0, Conversation::betweenUsers($userA->id, $userB->id)->get());
    }

    // ── scopeExcludingIgnoredAndTrashed ──

    public function test_scope_excluding_ignored_and_trashed_returns_all_when_none_excluded(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_one_id' => $user->id,
            'user_two_id' => $other->id,
        ]);

        $results = Conversation::forUser($user->id)
            ->excludingIgnoredAndTrashed($user->id)
            ->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($conversation));
    }

    public function test_scope_excluding_ignored_and_trashed_excludes_ignored_user_conversations(): void
    {
        $user = User::factory()->create();
        $ignored = User::factory()->create();
        $normal = User::factory()->create();

        Conversation::factory()->create([
            'user_one_id' => min($user->id, $ignored->id),
            'user_two_id' => max($user->id, $ignored->id),
        ]);
        $kept = Conversation::factory()->create([
            'user_one_id' => min($user->id, $normal->id),
            'user_two_id' => max($user->id, $normal->id),
        ]);

        Ignore::factory()->create([
            'ignorer_id' => $user->id,
            'ignored_id' => $ignored->id,
            'expires_at' => now()->addHour(),
        ]);

        $results = Conversation::forUser($user->id)
            ->excludingIgnoredAndTrashed($user->id)
            ->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($kept));
    }

    public function test_scope_excluding_ignored_and_trashed_excludes_trashed_contact_conversations(): void
    {
        $user = User::factory()->create();
        $trashedContact = User::factory()->create();
        $normal = User::factory()->create();

        Conversation::factory()->create([
            'user_one_id' => min($user->id, $trashedContact->id),
            'user_two_id' => max($user->id, $trashedContact->id),
        ]);
        $kept = Conversation::factory()->create([
            'user_one_id' => min($user->id, $normal->id),
            'user_two_id' => max($user->id, $normal->id),
        ]);

        $contact = Contact::factory()->accepted()->create([
            'user_id' => $user->id,
            'contact_user_id' => $trashedContact->id,
        ]);
        Trash::factory()->create([
            'user_id' => $user->id,
            'contact_id' => $contact->id,
        ]);

        $results = Conversation::forUser($user->id)
            ->excludingIgnoredAndTrashed($user->id)
            ->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($kept));
    }

    public function test_scope_excluding_ignored_and_trashed_does_not_exclude_expired_ignores(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_one_id' => min($user->id, $other->id),
            'user_two_id' => max($user->id, $other->id),
        ]);

        Ignore::factory()->create([
            'ignorer_id' => $user->id,
            'ignored_id' => $other->id,
            'expires_at' => now()->subHour(),
        ]);

        $results = Conversation::forUser($user->id)
            ->excludingIgnoredAndTrashed($user->id)
            ->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($conversation));
    }

    public function test_scope_excluding_ignored_and_trashed_excludes_both_simultaneously(): void
    {
        $user = User::factory()->create();
        $ignoredUser = User::factory()->create();
        $trashedUser = User::factory()->create();
        $normalUser = User::factory()->create();

        // Conversation with ignored user
        Conversation::factory()->create([
            'user_one_id' => min($user->id, $ignoredUser->id),
            'user_two_id' => max($user->id, $ignoredUser->id),
        ]);
        // Conversation with trashed user
        Conversation::factory()->create([
            'user_one_id' => min($user->id, $trashedUser->id),
            'user_two_id' => max($user->id, $trashedUser->id),
        ]);
        // Conversation with normal user
        $kept = Conversation::factory()->create([
            'user_one_id' => min($user->id, $normalUser->id),
            'user_two_id' => max($user->id, $normalUser->id),
        ]);

        Ignore::factory()->create([
            'ignorer_id' => $user->id,
            'ignored_id' => $ignoredUser->id,
            'expires_at' => now()->addHour(),
        ]);

        $contact = Contact::factory()->accepted()->create([
            'user_id' => $user->id,
            'contact_user_id' => $trashedUser->id,
        ]);
        Trash::factory()->create([
            'user_id' => $user->id,
            'contact_id' => $contact->id,
        ]);

        $results = Conversation::forUser($user->id)
            ->excludingIgnoredAndTrashed($user->id)
            ->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($kept));
    }

    // ── getOtherUser ──

    public function test_get_other_user_returns_user_two_when_given_user_one_id(): void
    {
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_one_id' => $userOne->id,
            'user_two_id' => $userTwo->id,
        ]);

        $this->assertTrue($conversation->getOtherUser($userOne->id)->is($userTwo));
    }

    public function test_get_other_user_returns_user_one_when_given_user_two_id(): void
    {
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_one_id' => $userOne->id,
            'user_two_id' => $userTwo->id,
        ]);

        $this->assertTrue($conversation->getOtherUser($userTwo->id)->is($userOne));
    }

    // ── hasParticipant ──

    public function test_has_participant_returns_true_for_user_one(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_one_id' => $user->id]);

        $this->assertTrue($conversation->hasParticipant($user->id));
    }

    public function test_has_participant_returns_true_for_user_two(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_two_id' => $user->id]);

        $this->assertTrue($conversation->hasParticipant($user->id));
    }

    public function test_has_participant_returns_false_for_non_participant(): void
    {
        $conversation = Conversation::factory()->create();
        $stranger = User::factory()->create();

        $this->assertFalse($conversation->hasParticipant($stranger->id));
    }
}
