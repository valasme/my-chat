<?php

namespace Tests\Unit\Models;

use App\Models\Contact;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_for_user_returns_only_notes_belonging_to_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = Note::factory()->create(['user_id' => $user->id]);
        Note::factory()->create(['user_id' => $other->id]);

        $results = Note::forUser($user->id)->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($mine));
    }

    public function test_scope_personal_returns_notes_without_contact(): void
    {
        $user = User::factory()->create();
        $personal = Note::factory()->create(['user_id' => $user->id, 'contact_id' => null]);
        $contact = Contact::factory()->accepted()->create(['user_id' => $user->id]);
        Note::factory()->create(['user_id' => $user->id, 'contact_id' => $contact->id]);

        $results = Note::forUser($user->id)->personal()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($personal));
    }

    public function test_scope_for_contact_returns_notes_for_that_contact(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->accepted()->create(['user_id' => $user->id]);
        $otherContact = Contact::factory()->accepted()->create(['user_id' => $user->id]);

        $noteForContact = Note::factory()->create(['user_id' => $user->id, 'contact_id' => $contact->id]);
        Note::factory()->create(['user_id' => $user->id, 'contact_id' => $otherContact->id]);
        Note::factory()->create(['user_id' => $user->id, 'contact_id' => null]);

        $results = Note::forContact($contact->id)->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($noteForContact));
    }

    public function test_scope_with_tag_returns_notes_containing_tag(): void
    {
        $user = User::factory()->create();
        $taggedNote = Note::factory()->create(['user_id' => $user->id, 'tags' => ['work', 'important']]);
        Note::factory()->create(['user_id' => $user->id, 'tags' => ['personal']]);
        Note::factory()->create(['user_id' => $user->id, 'tags' => []]);

        $results = Note::withTag('work')->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($taggedNote));
    }

    public function test_user_relationship_returns_correct_user(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($note->user->is($user));
    }

    public function test_contact_relationship_returns_null_for_personal_note(): void
    {
        $note = Note::factory()->create(['contact_id' => null]);

        $this->assertNull($note->contact);
    }

    public function test_contact_relationship_returns_contact_for_contact_note(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->accepted()->create(['user_id' => $user->id]);
        $note = Note::factory()->create(['user_id' => $user->id, 'contact_id' => $contact->id]);

        $this->assertTrue($note->contact->is($contact));
    }

    public function test_tags_are_cast_to_array(): void
    {
        $note = Note::factory()->create(['tags' => ['work', 'important']]);

        $this->assertIsArray($note->tags);
        $this->assertContains('work', $note->tags);
        $this->assertContains('important', $note->tags);
    }

    public function test_scope_for_user_excludes_soft_deleted_notes_by_default(): void
    {
        $user = User::factory()->create();
        Note::factory()->create(['user_id' => $user->id]);
        Note::factory()->trashed()->create(['user_id' => $user->id]);

        $results = Note::forUser($user->id)->get();

        $this->assertCount(1, $results);
    }
}
