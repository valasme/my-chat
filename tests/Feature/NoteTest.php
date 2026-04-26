<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    // ── Guest redirects ──

    public function test_guests_cannot_access_notes_index(): void
    {
        $this->get(route('notes.index'))->assertRedirect(route('login'));
    }

    public function test_guests_cannot_create_notes(): void
    {
        $this->get(route('notes.create'))->assertRedirect(route('login'));
    }

    public function test_guests_cannot_store_notes(): void
    {
        $this->post(route('notes.store'), ['title' => 'Test', 'body' => 'Body'])
            ->assertRedirect(route('login'));
    }

    // ── Index ──

    public function test_user_can_view_notes_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('notes.index'))
            ->assertOk();
    }

    // ── Create / Store ──

    public function test_user_can_view_create_note_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('notes.create'))
            ->assertOk();
    }

    public function test_user_can_create_personal_note(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('notes.store'), [
                'title' => 'My Note',
                'body' => 'Note body content.',
            ])
            ->assertRedirect(route('notes.index'));

        $this->assertDatabaseHas('notes', [
            'user_id' => $user->id,
            'title' => 'My Note',
            'body' => 'Note body content.',
            'contact_id' => null,
            'deleted_at' => null,
        ]);
    }

    public function test_user_can_create_contact_note(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $contact = Contact::factory()->accepted()->create([
            'user_id' => $user->id,
            'contact_user_id' => $other->id,
        ]);

        $this->actingAs($user)
            ->post(route('notes.store'), [
                'title' => 'Contact Note',
                'body' => 'About this contact.',
                'contact_id' => $contact->id,
            ])
            ->assertRedirect(route('notes.index'));

        $this->assertDatabaseHas('notes', [
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'title' => 'Contact Note',
        ]);
    }

    public function test_user_can_create_note_with_tags(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('notes.store'), [
                'title' => 'Tagged Note',
                'body' => 'Body',
                'tags' => 'work, important',
            ])
            ->assertRedirect(route('notes.index'));

        $note = Note::where('user_id', $user->id)->where('title', 'Tagged Note')->first();
        $this->assertNotNull($note);
        $this->assertContains('work', $note->tags);
        $this->assertContains('important', $note->tags);
    }

    public function test_store_requires_title(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('notes.store'), ['body' => 'Body'])
            ->assertSessionHasErrors('title');
    }

    public function test_store_requires_body(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('notes.store'), ['title' => 'Title'])
            ->assertSessionHasErrors('body');
    }

    public function test_user_cannot_create_note_with_another_users_contact(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();
        $contact = Contact::factory()->accepted()->create([
            'user_id' => $userB->id,
            'contact_user_id' => $userC->id,
        ]);

        $this->actingAs($userA)
            ->post(route('notes.store'), [
                'title' => 'Sneaky Note',
                'body' => 'Body',
                'contact_id' => $contact->id,
            ])
            ->assertSessionHasErrors('contact_id');
    }

    // ── Show ──

    public function test_owner_can_view_note(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('notes.show', $note))
            ->assertOk()
            ->assertSee($note->title);
    }

    public function test_other_user_cannot_view_note(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($outsider)
            ->get(route('notes.show', $note))
            ->assertForbidden();
    }

    public function test_trashed_note_returns_404_on_show(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->trashed()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('notes.show', $note->id))
            ->assertNotFound();
    }

    // ── Edit / Update ──

    public function test_owner_can_view_edit_form(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('notes.edit', $note))
            ->assertOk()
            ->assertSee($note->title);
    }

    public function test_other_user_cannot_view_edit_form(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($outsider)
            ->get(route('notes.edit', $note))
            ->assertForbidden();
    }

    public function test_owner_can_update_note(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->put(route('notes.update', $note), [
                'title' => 'Updated Title',
                'body' => 'Updated body.',
            ])
            ->assertRedirect(route('notes.show', $note));

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Updated Title',
            'body' => 'Updated body.',
        ]);
    }

    public function test_other_user_cannot_update_note(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($outsider)
            ->put(route('notes.update', $note), [
                'title' => 'Hacked',
                'body' => 'Hacked body.',
            ])
            ->assertForbidden();
    }

    // ── Destroy (soft delete) ──

    public function test_owner_can_delete_note_to_trash(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('notes.destroy', $note))
            ->assertRedirect(route('notes.index'));

        $this->assertSoftDeleted('notes', ['id' => $note->id]);
    }

    public function test_other_user_cannot_delete_note(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($outsider)
            ->delete(route('notes.destroy', $note))
            ->assertForbidden();
    }

    // ── Restore ──

    public function test_owner_can_restore_trashed_note(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->trashed()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('notes.restore', $note->id))
            ->assertRedirect(route('notes.index'));

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'deleted_at' => null,
        ]);
    }

    public function test_other_user_cannot_restore_note(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $note = Note::factory()->trashed()->create(['user_id' => $owner->id]);

        $this->actingAs($outsider)
            ->post(route('notes.restore', $note->id))
            ->assertForbidden();
    }

    // ── Force Delete ──

    public function test_owner_can_force_delete_trashed_note(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->trashed()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('notes.force-delete', $note->id))
            ->assertRedirect(route('notes.index'));

        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    public function test_other_user_cannot_force_delete_note(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $note = Note::factory()->trashed()->create(['user_id' => $owner->id]);

        $this->actingAs($outsider)
            ->delete(route('notes.force-delete', $note->id))
            ->assertForbidden();
    }

    // ── Misc ──

    public function test_note_is_created_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('notes.store'), [
            'title' => 'Auto Assign',
            'body' => 'Body text.',
        ]);

        $this->assertDatabaseHas('notes', [
            'user_id' => $user->id,
            'title' => 'Auto Assign',
        ]);
    }

    public function test_guests_cannot_delete_notes(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->delete(route('notes.destroy', $note))
            ->assertRedirect(route('login'));
    }
}
