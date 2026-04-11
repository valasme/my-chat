<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Ignore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IgnoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_ignores(): void
    {
        $this->get(route('ignores.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_ignored_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ignores.index'))
            ->assertOk();
    }

    public function test_user_can_ignore_with_preset_duration(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($userA)
            ->post(route('ignores.store'), [
                'user_id' => $userB->id,
                'duration' => '24h',
            ])
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseHas('ignores', [
            'ignorer_id' => $userA->id,
            'ignored_id' => $userB->id,
        ]);
    }

    public function test_user_can_ignore_with_custom_date(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Contact::factory()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
            'status' => 'accepted',
        ]);

        $expiresAt = now()->addDays(10)->format('Y-m-d H:i:s');

        $this->actingAs($userA)
            ->post(route('ignores.store'), [
                'user_id' => $userB->id,
                'duration' => 'custom',
                'expires_at' => $expiresAt,
            ])
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseHas('ignores', [
            'ignorer_id' => $userA->id,
            'ignored_id' => $userB->id,
        ]);
    }

    public function test_cannot_ignore_non_contact(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)
            ->post(route('ignores.store'), [
                'user_id' => $userB->id,
                'duration' => '1h',
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_user_can_cancel_ignore(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $ignore = Ignore::create([
            'ignorer_id' => $userA->id,
            'ignored_id' => $userB->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($userA)
            ->delete(route('ignores.destroy', $ignore))
            ->assertRedirect(route('ignores.index'));

        $this->assertDatabaseMissing('ignores', ['id' => $ignore->id]);
    }

    public function test_only_ignorer_can_cancel(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $ignore = Ignore::create([
            'ignorer_id' => $userA->id,
            'ignored_id' => $userB->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($userB)
            ->delete(route('ignores.destroy', $ignore))
            ->assertForbidden();
    }

    public function test_clean_expired_ignores_command(): void
    {
        Ignore::create([
            'ignorer_id' => User::factory()->create()->id,
            'ignored_id' => User::factory()->create()->id,
            'expires_at' => now()->subHour(),
        ]);

        Ignore::create([
            'ignorer_id' => User::factory()->create()->id,
            'ignored_id' => User::factory()->create()->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->artisan('app:clean-expired-ignores')
            ->assertSuccessful();

        $this->assertDatabaseCount('ignores', 1);
    }

    public function test_ignored_user_sees_unavailable_message(): void
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

        Ignore::create([
            'ignorer_id' => $userA->id,
            'ignored_id' => $userB->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($userB)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertSee('unavailable until');
    }
}
