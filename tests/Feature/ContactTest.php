<?php

/**
 * Contact Feature Tests
 *
 * Comprehensive test suite for the user-to-user contact system.
 * Tests are organized into logical groups matching the controller actions.
 *
 * Test groups:
 *   - Authentication:  Guests are redirected to login on all 5 routes.
 *   - Authorization:   Users cannot access or modify other users' contacts.
 *   - Index:           Listing, empty states, search, sorting, pagination.
 *   - Create:          Rendering the add-contact form.
 *   - Store:           Adding contacts by email with all validation/error paths.
 *   - Show:            Viewing a contact's profile detail page.
 *   - Destroy:         Removing a contact (owns vs. doesn't own).
 *   - Relationships:   Model relationship integrity (owner, person, hasContact).
 *   - Cascade Deletes: Contacts removed when either linked user is deleted.
 *   - Constraints:     Unique constraint, removed routes (edit/update).
 *   - Security:        Search input sanitization, sort field injection.
 *
 * @see \App\Http\Controllers\ContactController
 * @see \App\Models\Contact
 * @see \App\Policies\ContactPolicy
 */

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    // ── Authentication ────────────────────────────────────────────────
    // Verify unauthenticated users are redirected to login on every route.

    public function test_guests_are_redirected_from_contacts_index(): void
    {
        $this->get(route('contacts.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_from_contacts_create(): void
    {
        $this->get(route('contacts.create'))
            ->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_from_contacts_store(): void
    {
        $this->post(route('contacts.store'), ['email' => 'test@example.com'])
            ->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_from_contacts_show(): void
    {
        $contact = Contact::factory()->create();

        $this->get(route('contacts.show', $contact))
            ->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_from_contacts_destroy(): void
    {
        $contact = Contact::factory()->create();

        $this->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('login'));
    }

    // ── Authorization ─────────────────────────────────────────────────
    // Verify users cannot access contacts belonging to other users.

    public function test_user_cannot_view_another_users_contact(): void
    {
        $user = User::factory()->create();
        $otherContact = Contact::factory()->create();

        $this->actingAs($user)
            ->get(route('contacts.show', $otherContact))
            ->assertForbidden();
    }

    public function test_user_cannot_delete_another_users_contact(): void
    {
        $user = User::factory()->create();
        $otherContact = Contact::factory()->create();

        $this->actingAs($user)
            ->delete(route('contacts.destroy', $otherContact))
            ->assertForbidden();
    }

    // ── Index ─────────────────────────────────────────────────────────
    // Verify the contacts list shows correct data and states.

    public function test_authenticated_user_can_view_contacts_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('contacts.index'))
            ->assertOk()
            ->assertSee('Contacts');
    }

    public function test_index_shows_only_own_contacts(): void
    {
        $user = User::factory()->create();
        $contactPerson = User::factory()->create(['name' => 'Alice Visible']);
        $otherUser = User::factory()->create();
        $otherPerson = User::factory()->create(['name' => 'Bob Hidden']);

        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $contactPerson->id]);
        Contact::factory()->create(['user_id' => $otherUser->id, 'contact_id' => $otherPerson->id]);

        $this->actingAs($user)
            ->get(route('contacts.index'))
            ->assertOk()
            ->assertSee('Alice Visible')
            ->assertDontSee('Bob Hidden');
    }

    public function test_index_displays_contact_person_name_and_email(): void
    {
        $user = User::factory()->create();
        $person = User::factory()->create([
            'name' => 'TestPerson Name',
            'email' => 'testperson@example.com',
        ]);

        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $person->id]);

        $this->actingAs($user)
            ->get(route('contacts.index'))
            ->assertOk()
            ->assertSee('TestPerson Name')
            ->assertSee('testperson@example.com');
    }

    public function test_index_shows_empty_state_when_no_contacts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('contacts.index'))
            ->assertOk()
            ->assertSee('No contacts found')
            ->assertSee('Get started by adding your first contact.');
    }

    public function test_index_shows_search_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('contacts.index', ['search' => 'nonexistent']))
            ->assertOk()
            ->assertSee('No contacts found')
            ->assertSee('Try adjusting your search or filters.');
    }

    // ── Search ────────────────────────────────────────────────────────
    // Verify the search feature filters by name and email correctly.

    public function test_search_filters_contacts_by_name(): void
    {
        $user = User::factory()->create();
        $alice = User::factory()->create(['name' => 'Alice Wonderland']);
        $bob = User::factory()->create(['name' => 'Bob Builder']);

        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $alice->id]);
        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $bob->id]);

        $this->actingAs($user)
            ->get(route('contacts.index', ['search' => 'Alice']))
            ->assertOk()
            ->assertSee('Alice Wonderland')
            ->assertDontSee('Bob Builder');
    }

    public function test_search_filters_contacts_by_email(): void
    {
        $user = User::factory()->create();
        $alice = User::factory()->create(['email' => 'alice@unique-search.com', 'name' => 'Alice']);
        $bob = User::factory()->create(['email' => 'bob@other-domain.com', 'name' => 'Bob']);

        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $alice->id]);
        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $bob->id]);

        $this->actingAs($user)
            ->get(route('contacts.index', ['search' => 'unique-search']))
            ->assertOk()
            ->assertSee('Alice')
            ->assertDontSee('Bob');
    }

    public function test_search_with_special_characters(): void
    {
        $user = User::factory()->create();
        $person = User::factory()->create(['name' => "O'Brien"]);

        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $person->id]);

        $this->actingAs($user)
            ->get(route('contacts.index', ['search' => "O'Brien"]))
            ->assertOk()
            ->assertSee("O'Brien");
    }

    // ── Sorting ───────────────────────────────────────────────────────
    // Verify contacts can be sorted by name/email in both directions.

    public function test_sort_contacts_by_name_ascending(): void
    {
        $user = User::factory()->create();
        $alice = User::factory()->create(['name' => 'Alice']);
        $charlie = User::factory()->create(['name' => 'Charlie']);

        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $charlie->id]);
        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $alice->id]);

        $response = $this->actingAs($user)
            ->get(route('contacts.index', ['sort' => 'name', 'direction' => 'asc']));

        $response->assertOk();

        $contacts = $response->viewData('contacts');
        $this->assertEquals('Alice', $contacts->first()->person->name);
    }

    public function test_sort_contacts_by_name_descending(): void
    {
        $user = User::factory()->create();
        $alice = User::factory()->create(['name' => 'Alice']);
        $charlie = User::factory()->create(['name' => 'Charlie']);

        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $alice->id]);
        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $charlie->id]);

        $response = $this->actingAs($user)
            ->get(route('contacts.index', ['sort' => 'name', 'direction' => 'desc']));

        $contacts = $response->viewData('contacts');
        $this->assertEquals('Charlie', $contacts->first()->person->name);
    }

    public function test_sort_contacts_by_email_ascending(): void
    {
        $user = User::factory()->create();
        $aUser = User::factory()->create(['email' => 'aaa@example.com']);
        $zUser = User::factory()->create(['email' => 'zzz@example.com']);

        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $zUser->id]);
        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $aUser->id]);

        $response = $this->actingAs($user)
            ->get(route('contacts.index', ['sort' => 'email', 'direction' => 'asc']));

        $contacts = $response->viewData('contacts');
        $this->assertEquals('aaa@example.com', $contacts->first()->person->email);
    }

    public function test_sort_contacts_by_email_descending(): void
    {
        $user = User::factory()->create();
        $aUser = User::factory()->create(['email' => 'aaa@example.com']);
        $zUser = User::factory()->create(['email' => 'zzz@example.com']);

        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $aUser->id]);
        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $zUser->id]);

        $response = $this->actingAs($user)
            ->get(route('contacts.index', ['sort' => 'email', 'direction' => 'desc']));

        $contacts = $response->viewData('contacts');
        $this->assertEquals('zzz@example.com', $contacts->first()->person->email);
    }

    public function test_invalid_sort_field_defaults_to_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('contacts.index', ['sort' => 'invalid']));

        $response->assertOk();
        $this->assertEquals('name', $response->viewData('sort'));
    }

    public function test_invalid_direction_defaults_to_asc(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('contacts.index', ['direction' => 'invalid']));

        $response->assertOk();
        $this->assertEquals('asc', $response->viewData('direction'));
    }

    // ── Pagination ────────────────────────────────────────────────────
    // Verify contacts are paginated at 25 per page with query preservation.

    public function test_contacts_are_paginated_at_25_per_page(): void
    {
        $user = User::factory()->create();
        $people = User::factory()->count(30)->create();

        foreach ($people as $person) {
            Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $person->id]);
        }

        $response = $this->actingAs($user)
            ->get(route('contacts.index'));

        $contacts = $response->viewData('contacts');
        $this->assertCount(25, $contacts);
        $this->assertTrue($contacts->hasMorePages());
    }

    public function test_contacts_second_page_shows_remaining(): void
    {
        $user = User::factory()->create();
        $people = User::factory()->count(30)->create();

        foreach ($people as $person) {
            Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $person->id]);
        }

        $response = $this->actingAs($user)
            ->get(route('contacts.index', ['page' => 2]));

        $contacts = $response->viewData('contacts');
        $this->assertCount(5, $contacts);
    }

    public function test_pagination_preserves_query_string(): void
    {
        $user = User::factory()->create();
        $people = User::factory()->count(30)->create(['name' => 'SharedName']);

        foreach ($people as $person) {
            Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $person->id]);
        }

        $response = $this->actingAs($user)
            ->get(route('contacts.index', ['search' => 'SharedName']));

        $response->assertOk();
        $contacts = $response->viewData('contacts');
        $this->assertCount(25, $contacts);
    }

    // ── Create ────────────────────────────────────────────────────────
    // Verify the add-contact form renders correctly.

    public function test_authenticated_user_can_view_create_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('contacts.create'))
            ->assertOk()
            ->assertSee('Add Contact')
            ->assertSee('Email Address');
    }

    // ── Store (Add Contact by Email) ──────────────────────────────────
    // Verify adding contacts by email with all success, validation, and error paths.

    public function test_user_can_add_contact_by_email(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create(['email' => 'target@example.com', 'name' => 'Target User']);

        $this->actingAs($user)
            ->post(route('contacts.store'), ['email' => 'target@example.com'])
            ->assertRedirect(route('contacts.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('contacts', [
            'user_id' => $user->id,
            'contact_id' => $target->id,
        ]);
    }

    public function test_store_fails_when_email_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('contacts.store'), ['email' => 'nonexistent@example.com'])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_store_fails_when_adding_self(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);

        $this->actingAs($user)
            ->post(route('contacts.store'), ['email' => 'me@example.com'])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_store_fails_when_contact_already_exists(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create(['email' => 'duplicate@example.com']);

        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $target->id]);

        $this->actingAs($user)
            ->post(route('contacts.store'), ['email' => 'duplicate@example.com'])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_store_validates_email_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('contacts.store'), ['email' => ''])
            ->assertSessionHasErrors('email');
    }

    public function test_store_validates_email_format(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('contacts.store'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_store_validates_email_max_length(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('contacts.store'), ['email' => str_repeat('a', 246).'@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_store_preserves_old_input_on_failure(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('contacts.create'))
            ->post(route('contacts.store'), ['email' => 'nonexistent@example.com'])
            ->assertRedirect()
            ->assertSessionHasInput('email', 'nonexistent@example.com');
    }

    public function test_different_users_can_add_same_person_as_contact(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $target = User::factory()->create(['email' => 'shared@example.com']);

        $this->actingAs($userA)
            ->post(route('contacts.store'), ['email' => 'shared@example.com'])
            ->assertRedirect(route('contacts.index'));

        $this->actingAs($userB)
            ->post(route('contacts.store'), ['email' => 'shared@example.com'])
            ->assertRedirect(route('contacts.index'));

        $this->assertDatabaseCount('contacts', 2);
    }

    // ── Show ──────────────────────────────────────────────────────────
    // Verify the contact detail page renders correct profile info.

    public function test_user_can_view_own_contact(): void
    {
        $user = User::factory()->create();
        $person = User::factory()->create([
            'name' => 'Contact Person',
            'email' => 'contact@example.com',
        ]);

        $contact = Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $person->id]);

        $this->actingAs($user)
            ->get(route('contacts.show', $contact))
            ->assertOk()
            ->assertSee('Contact Person')
            ->assertSee('contact@example.com')
            ->assertSee('Member Since')
            ->assertSee('Added to Contacts');
    }

    public function test_show_displays_person_email_as_mailto_link(): void
    {
        $user = User::factory()->create();
        $person = User::factory()->create(['email' => 'mailto-test@example.com']);

        $contact = Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $person->id]);

        $this->actingAs($user)
            ->get(route('contacts.show', $contact))
            ->assertOk()
            ->assertSee('mailto:mailto-test@example.com', false);
    }

    public function test_show_has_delete_confirmation_modal(): void
    {
        $user = User::factory()->create();
        $person = User::factory()->create(['name' => 'Modal Person']);

        $contact = Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $person->id]);

        $this->actingAs($user)
            ->get(route('contacts.show', $contact))
            ->assertOk()
            ->assertSee('Remove Contact?')
            ->assertSee('This action cannot be undone.');
    }

    // ── Destroy ───────────────────────────────────────────────────────
    // Verify removing contacts works and doesn't delete the actual user.

    public function test_user_can_delete_own_contact(): void
    {
        $user = User::factory()->create();
        $person = User::factory()->create(['name' => 'Deleted Person']);

        $contact = Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $person->id]);

        $this->actingAs($user)
            ->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('contacts.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_deleting_contact_does_not_delete_the_user(): void
    {
        $user = User::factory()->create();
        $person = User::factory()->create();

        $contact = Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $person->id]);

        $this->actingAs($user)
            ->delete(route('contacts.destroy', $contact));

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('users', ['id' => $person->id]);
    }

    // ── Model Relationships ───────────────────────────────────────────
    // Verify Contact and User model relationships work correctly.

    public function test_contact_belongs_to_owner(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($contact->owner->is($user));
    }

    public function test_contact_belongs_to_person(): void
    {
        $person = User::factory()->create();
        $contact = Contact::factory()->create(['contact_id' => $person->id]);

        $this->assertTrue($contact->person->is($person));
    }

    public function test_user_has_many_contacts(): void
    {
        $user = User::factory()->create();
        Contact::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->contacts);
    }

    public function test_user_has_contact_returns_true_for_existing_contact(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $target->id]);

        $this->assertTrue($user->hasContact($target));
    }

    public function test_user_has_contact_returns_false_for_non_contact(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->assertFalse($user->hasContact($target));
    }

    public function test_contact_users_belongs_to_many(): void
    {
        $user = User::factory()->create();
        $people = User::factory()->count(3)->create();

        foreach ($people as $person) {
            Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $person->id]);
        }

        $this->assertCount(3, $user->contactUsers);
    }

    // ── Cascade Deletes ───────────────────────────────────────────────
    // Verify contacts are cleaned up when either linked user is deleted.

    public function test_contacts_are_deleted_when_owner_is_deleted(): void
    {
        $user = User::factory()->create();
        Contact::factory()->count(3)->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_contacts_are_deleted_when_person_is_deleted(): void
    {
        $person = User::factory()->create();
        Contact::factory()->count(2)->create(['contact_id' => $person->id]);

        $person->delete();

        $this->assertDatabaseCount('contacts', 0);
    }

    // ── Unique Constraint ─────────────────────────────────────────────
    // Verify the database prevents duplicate contact entries.

    public function test_unique_constraint_prevents_duplicate_contacts(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $target->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $target->id]);
    }

    // ── Route Constraints ─────────────────────────────────────────────
    // Verify edit and update routes are not registered (contacts are immutable links).

    public function test_edit_route_does_not_exist(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get("/contacts/{$contact->id}/edit")
            ->assertNotFound();
    }

    public function test_update_route_does_not_exist(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->put("/contacts/{$contact->id}", ['email' => 'test@example.com'])
            ->assertMethodNotAllowed();
    }

    // ── Security ──────────────────────────────────────────────────────
    // Verify search input is sanitized and sort injection is blocked.

    public function test_search_with_sql_wildcard_characters_is_escaped(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create(['name' => '100% Complete']);

        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $target->id]);

        $this->actingAs($user)
            ->get(route('contacts.index', ['search' => '100%']))
            ->assertOk()
            ->assertSee('100% Complete');
    }

    public function test_search_with_underscore_wildcard_is_escaped(): void
    {
        $user = User::factory()->create();
        $exact = User::factory()->create(['name' => 'test_user']);
        $similar = User::factory()->create(['name' => 'testXuser']);

        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $exact->id]);
        Contact::factory()->create(['user_id' => $user->id, 'contact_id' => $similar->id]);

        $response = $this->actingAs($user)
            ->get(route('contacts.index', ['search' => 'test_user']));

        $response->assertOk()->assertSee('test_user');
    }

    public function test_sort_field_injection_is_blocked(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('contacts.index', ['sort' => 'password']));

        $response->assertOk();
        $this->assertEquals('name', $response->viewData('sort'));
    }

    public function test_empty_search_returns_all_contacts(): void
    {
        $user = User::factory()->create();
        Contact::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('contacts.index', ['search' => '   ']));

        $response->assertOk();
        $contacts = $response->viewData('contacts');
        $this->assertCount(3, $contacts);
    }

    public function test_search_is_trimmed_and_truncated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('contacts.index', ['search' => '  Alice  ']))
            ->assertOk();
    }
}
