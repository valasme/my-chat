# Notes System — Design Spec

**Date:** 2026-04-26
**Status:** Approved

---

## Problem Statement

The chat application needs a notes system so users can capture personal notes and annotations tied to specific contacts. Notes must support titles, body text, optional tags, A-Z/Z-A sorting, and a trash/restore workflow via soft deletes.

---

## Scope

- Personal notes (not attached to any contact)
- Contact notes (attached to a specific accepted contact)
- Full CRUD with a dedicated `/notes` section in the sidebar
- Livewire-powered index (reactive search, filter, sort)
- Standard form-based create/edit pages (no inline editing)
- Soft-delete trash: notes deleted go to a trash tab; users can restore or permanently delete
- Tags stored as a JSON column (no separate tags table)

Out of scope:
- Note sharing between users
- Rich text / markdown rendering
- Note attachments/files
- Integration with the existing `trashes` table (contact-specific trash is unchanged)

---

## Data Model

### `notes` table

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint | PK, auto-increment |
| `user_id` | unsignedBigInt | FK → users (cascadeOnDelete) |
| `contact_id` | unsignedBigInt, nullable | FK → contacts (nullOnDelete — deleting a contact keeps the note, sets contact_id to null) |
| `title` | string | required |
| `body` | text | required |
| `tags` | JSON | default `[]` |
| `deleted_at` | datetime, nullable | SoftDeletes |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:**
- `(user_id, deleted_at)` — efficient listing of a user's active/trashed notes
- `contact_id` — lookup of notes for a specific contact

---

## Architecture

### Backend Files

| Layer | Path |
|---|---|
| Migration | `database/migrations/YYYY_create_notes_table.php` |
| Model | `app/Models/Note.php` |
| Factory | `database/factories/NoteFactory.php` |
| Seeder | `database/seeders/NoteSeeder.php` |
| Concern | `app/Concerns/NoteValidationRules.php` |
| Store Request | `app/Http/Requests/StoreNoteRequest.php` |
| Update Request | `app/Http/Requests/UpdateNoteRequest.php` |
| Policy | `app/Policies/NotePolicy.php` |
| Controller | `app/Http/Controllers/NoteController.php` |
| Livewire | `app/Livewire/NoteIndex.php` |

### Frontend Files

| Layer | Path |
|---|---|
| Notes index | `resources/views/notes/index.blade.php` |
| Notes create | `resources/views/notes/create.blade.php` |
| Notes show | `resources/views/notes/show.blade.php` |
| Notes edit | `resources/views/notes/edit.blade.php` |
| Livewire view | `resources/views/livewire/note-index.blade.php` |

---

## Model (`Note`)

```php
// Traits: HasFactory, SoftDeletes
// Fillable: user_id, contact_id, title, body, tags
// Casts: tags → array

// Relationships:
//   user() → BelongsTo User
//   contact() → BelongsTo Contact (nullable)

// Scopes:
//   scopeForUser(int $userId)   — where user_id = $userId
//   scopePersonal()             — where contact_id is null
//   scopeForContact(int $contactId) — where contact_id = $contactId
//   scopeWithTag(string $tag)   — JSON_CONTAINS(tags, tag)
```

---

## Controller (`NoteController`)

| Method | Route | Description |
|---|---|---|
| `index` | GET /notes | Renders blade view that mounts NoteIndex Livewire component |
| `create` | GET /notes/create | Create form |
| `store` | POST /notes | Validate & save, redirect to index |
| `show` | GET /notes/{note} | Display single note |
| `edit` | GET /notes/{note}/edit | Edit form (pre-filled) |
| `update` | PUT /notes/{note} | Validate & update, redirect to show |
| `destroy` | DELETE /notes/{note} | Soft delete (move to trash), redirect to index |
| `restore` | POST /notes/{note}/restore | Restore from trash, redirect to index |
| `forceDelete` | DELETE /notes/{note}/force-delete | Permanently delete, redirect to index |

The `index` action simply returns the view; all listing/search/sort logic lives in the Livewire component.

---

## Policy (`NotePolicy`)

| Gate | Logic |
|---|---|
| `viewAny` | Any authenticated user |
| `view` | `note->user_id === $user->id` |
| `create` | Any authenticated user |
| `update` | `note->user_id === $user->id` |
| `delete` | `note->user_id === $user->id` |
| `restore` | `note->user_id === $user->id` |
| `forceDelete` | `note->user_id === $user->id` |

---

## Validation Concern (`NoteValidationRules`)

```php
protected function noteRules(): array
{
    return [
        'title' => ['required', 'string', 'max:255'],
        'body'  => ['required', 'string', 'max:10000'],
        'tags'  => ['nullable', 'array'],
        'tags.*' => ['string', 'max:50'],
        'contact_id' => ['nullable', 'exists:contacts,id'],
    ];
}
```

---

## Livewire Component (`NoteIndex`)

**Properties:**
- `string $search` — live search against note titles
- `string $filter` — `all` | `personal` | `contact` (filter by type)
- `string $sort` — `latest` | `az` | `za`
- `string $view` — `active` | `trashed`

**Behavior:**
- Debounced `$search` on every keypress
- All properties drive a re-render via `#[Computed]` `notes()` property
- Uses `withTrashed()` scope when `$view === 'trashed'`, `onlyTrashed()` to show trashed-only
- Table columns: Title, Contact (if attached), Created At, Last Updated, Actions

---

## Routes

```php
// In auth middleware group:

// Read
Route::middleware('throttle:chat-read')->group(function () {
    Route::resource('notes', NoteController::class)->only(['index', 'create', 'show', 'edit']);
});

// Write
Route::middleware('throttle:chat-write')->group(function () {
    Route::resource('notes', NoteController::class)->only(['store', 'update', 'destroy']);
    Route::post('notes/{note}/restore', [NoteController::class, 'restore'])->name('notes.restore');
    Route::delete('notes/{note}/force-delete', [NoteController::class, 'forceDelete'])->name('notes.force-delete');
});
```

---

## Sidebar

Add a "Notes" item to the existing "Chat" sidebar group:

```blade
<flux:sidebar.item icon="pencil-square" :href="route('notes.index')" :current="request()->routeIs('notes.*')" wire:navigate>
    {{ __('Notes') }}
</flux:sidebar.item>
```

---

## Seeder (`NoteSeeder`)

- Create ~10 personal notes for the `test@example.com` user with varied titles/bodies/tags
- Create ~5 contact-linked notes attached to accepted contacts
- Create ~2 trashed notes (soft-deleted) to demonstrate the trash tab

---

## Tests

### Feature: `tests/Feature/NoteTest.php`

- Unauthenticated users are redirected
- Index returns 200 for authenticated user
- Create returns 200
- Store creates a note and redirects
- Store fails validation (missing title, missing body)
- Show returns 200 for owner, 403 for other user
- Edit returns 200 for owner, 403 for other user
- Update updates note and redirects
- Destroy soft-deletes and redirects
- Restore restores the note
- ForceDelete permanently removes the note
- `contact_id` is optional (personal notes work without it)
- Tags are stored and retrieved correctly

### Unit: `tests/Unit/Models/NoteTest.php`

- `scopeForUser` — only returns notes for the given user
- `scopePersonal` — only returns notes without a contact
- `scopeForContact` — only returns notes for a given contact
- `user` relationship — returns correct user
- `contact` relationship — returns correct contact or null

---

## Error Handling

- Gate authorization failures return 403 (Laravel default)
- Validation errors redirect back with errors (standard `FormRequest` behavior)
- Accessing a soft-deleted note via show/edit with no special scope returns 404 (model binding uses default scope)

---

## Open Questions Resolved

| Question | Decision |
|---|---|
| Trash approach | Soft deletes on notes — independent from contacts trash table |
| Tags storage | JSON column (no separate table) — simpler, sufficient for current scale |
| Edit experience | Standard form-based edit page (no inline) |
| Index list | Livewire component for reactive search/filter/sort |
| Note fields shown in index | Title, created_at, updated_at (table view) |
| Pinning | Not included — A-Z / Z-A sort only |
