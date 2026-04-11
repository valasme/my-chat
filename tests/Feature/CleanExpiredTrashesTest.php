<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Trash;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CleanExpiredTrashesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_expired_trash_deletes_contact_and_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->accepted()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
        ]);

        $lower = min($userA->id, $userB->id);
        $upper = max($userA->id, $userB->id);
        $conversation = Conversation::factory()->create([
            'user_one_id' => $lower,
            'user_two_id' => $upper,
        ]);

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userA->id,
        ]);

        Trash::factory()->create([
            'user_id' => $userA->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('app:clean-expired-trashes')
            ->expectsOutputToContain('Processed 1 expired trash record(s)')
            ->assertExitCode(0);

        $this->assertDatabaseCount('trashes', 0);
        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_non_expired_trash_is_not_deleted(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->accepted()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
        ]);

        Trash::factory()->create([
            'user_id' => $userA->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->addWeek(),
        ]);

        $this->artisan('app:clean-expired-trashes')
            ->expectsOutputToContain('Processed 0 expired trash record(s)')
            ->assertExitCode(0);

        $this->assertDatabaseCount('trashes', 1);
        $this->assertDatabaseCount('contacts', 1);
    }
}
