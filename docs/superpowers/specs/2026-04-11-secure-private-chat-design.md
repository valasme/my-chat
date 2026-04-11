# Secure Private Chat Application — Design Specification

## Overview

A secure, private one-to-one chat application built on Laravel 13 with Livewire 4 and Flux UI v2. Users authenticate, manage contacts by email, and exchange encrypted text messages. The system emphasizes privacy: no notifications, no read receipts, simple polling, symmetric contact relationships, and application-level encryption for all message content.

## Core Principles

- **Privacy first**: No notifications, no read receipts, no delivery status, encrypted messages
- **Symmetric contacts**: Accept applies to both users; delete applies to both users
- **Per-user trash**: Trash is one-directional; each user controls their own trash independently
- **Feature isolation**: Each feature (Contact, Block, Ignore, Trash, Conversation, Message) has its own model, controller, policy, factory, seeder, form requests, validation concern, and views

## Architecture: Fully Separate Models

Six independent models, each with a full file set following the existing Contact scaffold pattern.

---

## Data Models & Database Schema

### `contacts` table

| Column           | Type      | Notes                                      |
|------------------|-----------|--------------------------------------------|
| `id`             | bigint PK | Auto-increment                             |
| `user_id`        | foreignId | The user who sent the request (requester)  |
| `contact_user_id`| foreignId | The user who received the request          |
| `status`         | string    | Enum: `pending`, `accepted`                |
| `created_at`     | timestamp |                                            |
| `updated_at`     | timestamp |                                            |

- Unique constraint: `[user_id, contact_user_id]`
- Foreign keys to `users.id` with cascade on delete
- A single row represents the bidirectional relationship once accepted

### `blocks` table

| Column       | Type      | Notes                          |
|--------------|-----------|--------------------------------|
| `id`         | bigint PK | Auto-increment                 |
| `blocker_id` | foreignId | The user who blocked           |
| `blocked_id` | foreignId | The user who was blocked       |
| `created_at` | timestamp |                                |
| `updated_at` | timestamp |                                |

- Unique constraint: `[blocker_id, blocked_id]`
- Foreign keys to `users.id` with cascade on delete

### `ignores` table

| Column       | Type      | Notes                              |
|--------------|-----------|------------------------------------|
| `id`         | bigint PK | Auto-increment                     |
| `ignorer_id` | foreignId | The user who is ignoring           |
| `ignored_id` | foreignId | The user being ignored             |
| `expires_at` | datetime  | When the ignore period ends        |
| `created_at` | timestamp |                                    |
| `updated_at` | timestamp |                                    |

- Unique constraint: `[ignorer_id, ignored_id]`
- Foreign keys to `users.id` with cascade on delete

### `conversations` table

| Column        | Type      | Notes                                  |
|---------------|-----------|----------------------------------------|
| `id`          | bigint PK | Auto-increment                         |
| `user_one_id` | foreignId | First participant (lower ID by convention) |
| `user_two_id` | foreignId | Second participant                     |
| `created_at`  | timestamp |                                        |
| `updated_at`  | timestamp |                                        |

- Unique constraint: `[user_one_id, user_two_id]`
- Foreign keys to `users.id` with cascade on delete
- Created automatically when a contact request is accepted

### `messages` table

| Column            | Type      | Notes                                         |
|-------------------|-----------|-----------------------------------------------|
| `id`              | bigint PK | Auto-increment                                |
| `conversation_id` | foreignId | FK to conversations                           |
| `sender_id`       | foreignId | FK to users (who sent the message)            |
| `body`            | text      | Encrypted via Laravel `encrypt()` before store |
| `created_at`      | timestamp |                                                |
| `updated_at`      | timestamp |                                                |

- Foreign key to `conversations.id` with cascade on delete
- Foreign key to `users.id` with cascade on delete
- No read receipts, no delivery status columns

### `trashes` table

| Column          | Type      | Notes                                            |
|-----------------|-----------|--------------------------------------------------|
| `id`            | bigint PK | Auto-increment                                   |
| `user_id`       | foreignId | The user who moved the contact to trash          |
| `contact_id`    | foreignId | FK to contacts table                             |
| `expires_at`    | datetime  | When auto-deletion occurs                        |
| `is_quick_delete` | boolean | If true, conversation messages were wiped on creation |
| `created_at`    | timestamp |                                                  |
| `updated_at`    | timestamp |                                                  |

- Unique constraint: `[user_id, contact_id]`
- Foreign keys with cascade on delete

---

## Business Logic & Feature Interactions

### Contact Request Flow

1. User A enters User B's email on the Contact Create page
2. `StoreContactRequest` validates: email exists in users table, not self, no existing contact (in either direction), not blocked by User B
3. Contact created: `user_id: A`, `contact_user_id: B`, `status: pending`
4. User B sees incoming request on Contacts Index page → can **Accept** or **Decline**
5. **Accept**: status → `accepted`, Conversation auto-created, both users see each other as contacts
6. **Decline**: Contact record deleted

### Blocking Flow

1. User A blocks User B (available from contact list or pending requests — requires existing contact/pending relationship)
2. System cascading actions:
   - Deletes Contact record
   - Deletes Conversation and all Messages between A and B
   - Deletes any Trash record for this contact
   - Deletes any Ignore record between A and B
   - Creates Block record (`blocker_id: A`, `blocked_id: B`)
3. User B sees a "blocked" indicator if they attempt any interaction
4. User A manages blocks from the Blocked Users list → **Unblock** deletes the Block record
5. After unblocking, User B can send a new contact request to User A

### Ignore Flow

1. User A ignores User B with a duration:
   - Presets: 1 hour, 8 hours, 24 hours, 3 days, 7 days
   - Custom: date picker for any future date/time
2. Ignore record created: `ignorer_id: A`, `ignored_id: B`, `expires_at: [calculated]`
3. **Effect on User A**: Conversation with B is hidden from conversations list; contact shows "Ignored until [date]"
4. **Effect on User B**: Sees "User A is unavailable until [date]", cannot send messages
5. **Expiry**: Scheduled command `CleanExpiredIgnores` deletes ignore records where `expires_at < now()`
6. After expiry: conversation becomes visible again, messaging resumes

### Trash Flow

1. **Normal trash**: User A moves contact to trash, selects expiry period:
   - Presets: 7 days, 14 days, 30 days, 60 days
   - Custom: date picker for any future date
   - Trash record created. User A's conversation hidden. User B can still send messages (they pile up unseen for A).

2. **Quick delete**: Same as normal trash, but also immediately deletes all Messages in the Conversation. `is_quick_delete: true`. If reverted, conversation is restored but empty.

3. **Revert/Restore**: User A restores from Trash Index. Trash record deleted. User A sees the conversation again (including any messages B sent during the trash period — unless it was a quick delete).

4. **Expiry**: Scheduled command `CleanExpiredTrashes`:
   - For each expired trash record: hard deletes Contact + Conversation + Messages for both users
   - Then deletes the Trash record

### Messaging Flow

1. User opens Conversation Show page, sees message history (decrypted on read via `decrypt()`)
2. Sends message: text encrypted via `encrypt()`, stored in `messages` table
3. Page polls every 5 seconds via Livewire `wire:poll.5s` to fetch new messages
4. **Pre-send checks**: contact is accepted, sender not blocked by recipient, sender not ignored by recipient, contact not in sender's trash

### Precedence Rules for Conflicting States

- **Block supersedes everything**: blocking immediately hard deletes all relationships (contact, conversation, messages, trash, ignore)
- **Trash takes precedence over ignore**: if a contact is both trashed and ignored, trash behavior applies
- **Ignore is temporary**: ignore only affects visibility/messaging during its duration

---

## File Structure

### Contact (existing scaffolds — implement)

| File | Purpose |
|------|---------|
| `app/Models/Contact.php` | Model with relationships, scopes, fillable |
| `app/Http/Controllers/ContactController.php` | index, create, store, show (with accept/decline) |
| `app/Policies/ContactPolicy.php` | Authorization rules |
| `app/Http/Requests/StoreContactRequest.php` | Email validation, duplicate/block checks |
| `app/Http/Requests/UpdateContactRequest.php` | Accept/decline validation |
| `app/Concerns/ContactValidationRules.php` | Shared validation rules |
| `database/factories/ContactFactory.php` | States: pending, accepted |
| `database/seeders/ContactSeeder.php` | Sample data |
| `database/migrations/2026_04_11_172736_create_contacts_table.php` | Schema (update existing) |
| `resources/views/contacts/index.blade.php` | Contact list + pending requests |
| `resources/views/contacts/create.blade.php` | Add contact form |
| `resources/views/contacts/show.blade.php` | Contact detail with actions |
| `resources/views/contacts/edit.blade.php` | Edit contact (if needed) |

### Block (new)

| File | Purpose |
|------|---------|
| `app/Models/Block.php` | Model |
| `app/Http/Controllers/BlockController.php` | index (blocked list), store (block), destroy (unblock) |
| `app/Policies/BlockPolicy.php` | Authorization |
| `app/Http/Requests/StoreBlockRequest.php` | Validation |
| `app/Concerns/BlockValidationRules.php` | Shared rules |
| `database/factories/BlockFactory.php` | Factory |
| `database/seeders/BlockSeeder.php` | Seeder |
| `database/migrations/..._create_blocks_table.php` | Schema |
| `resources/views/blocks/index.blade.php` | Blocked users list with unblock action |

### Ignore (new)

| File | Purpose |
|------|---------|
| `app/Models/Ignore.php` | Model |
| `app/Http/Controllers/IgnoreController.php` | store (ignore), destroy (cancel ignore) |
| `app/Policies/IgnorePolicy.php` | Authorization |
| `app/Http/Requests/StoreIgnoreRequest.php` | Duration validation |
| `app/Concerns/IgnoreValidationRules.php` | Shared rules |
| `database/factories/IgnoreFactory.php` | Factory |
| `database/seeders/IgnoreSeeder.php` | Seeder |
| `database/migrations/..._create_ignores_table.php` | Schema |
| `resources/views/ignores/index.blade.php` | Ignored users list (minimal) |

### Trash (new)

| File | Purpose |
|------|---------|
| `app/Models/Trash.php` | Model |
| `app/Http/Controllers/TrashController.php` | index (trash list), store (trash), destroy (restore), forceDelete |
| `app/Policies/TrashPolicy.php` | Authorization |
| `app/Http/Requests/StoreTrashRequest.php` | Period validation |
| `app/Concerns/TrashValidationRules.php` | Shared rules |
| `database/factories/TrashFactory.php` | Factory |
| `database/seeders/TrashSeeder.php` | Seeder |
| `database/migrations/..._create_trashes_table.php` | Schema |
| `resources/views/trashes/index.blade.php` | Trashed contacts list with restore/delete actions |

### Conversation (new)

| File | Purpose |
|------|---------|
| `app/Models/Conversation.php` | Model |
| `app/Http/Controllers/ConversationController.php` | index (conversation list), show (chat view) |
| `app/Policies/ConversationPolicy.php` | Authorization |
| `database/factories/ConversationFactory.php` | Factory |
| `database/seeders/ConversationSeeder.php` | Seeder |
| `database/migrations/..._create_conversations_table.php` | Schema |
| `resources/views/conversations/index.blade.php` | Conversations list |
| `resources/views/conversations/show.blade.php` | Chat view with messages + input |

### Message (new)

| File | Purpose |
|------|---------|
| `app/Models/Message.php` | Model with encryption accessors |
| `app/Http/Controllers/MessageController.php` | store (send message) |
| `app/Policies/MessagePolicy.php` | Authorization |
| `app/Http/Requests/StoreMessageRequest.php` | Message validation |
| `app/Concerns/MessageValidationRules.php` | Shared rules |
| `database/factories/MessageFactory.php` | Factory |
| `database/seeders/MessageSeeder.php` | Seeder |
| `database/migrations/..._create_messages_table.php` | Schema |

### Scheduled Commands (new)

| File | Purpose |
|------|---------|
| `app/Console/Commands/CleanExpiredIgnores.php` | Deletes expired ignore records |
| `app/Console/Commands/CleanExpiredTrashes.php` | Hard deletes expired trashes + cascading |

---

## UI & Navigation

### Sidebar Navigation

Added to `resources/views/layouts/app/sidebar.blade.php`:

```
Platform
  ├─ Dashboard (existing)
  ├─ Contacts        → contacts.index
  ├─ Messages        → conversations.index
  ├─ Trash           → trashes.index
  └─ Blocked         → blocks.index
```

### Page Descriptions

1. **Contacts Index** (`contacts.index`): Lists accepted contacts and pending requests (incoming/outgoing). Actions per contact: Message, Block, Ignore, Trash, Quick Delete. "Add Contact" button links to create form.

2. **Contact Create** (`contacts.create`): Form with email input to send a new contact request.

3. **Contact Show** (`contacts.show`): View contact info with action buttons: Message, Block, Ignore, Trash.

4. **Messages/Conversations Index** (`conversations.index`): List of active conversations showing contact name and last message timestamp. Click opens the conversation.

5. **Conversation Show** (`conversations.show`): Message history (scrollable, newest at bottom) with text input box and send button. Uses `wire:poll.5s` to fetch new messages.

6. **Trash Index** (`trashes.index`): List of trashed contacts with expiry dates. Actions: Restore, Delete Now.

7. **Blocked Index** (`blocks.index`): List of blocked users. Action: Unblock.

### Polling Strategy

- `wire:poll.5s` on Conversation Show page (refreshes messages)
- `wire:poll.5s` on Contacts Index (refreshes pending request status)
- No websockets, no push notifications

---

## Security

- **Message encryption**: All message bodies encrypted with Laravel's `encrypt()` before database storage, decrypted with `decrypt()` on read. The `Message` model uses an Eloquent accessor/mutator for transparent encryption.
- **Policy enforcement**: Every controller action authorized via corresponding Policy. Users can only access their own contacts, conversations, blocks, ignores, and trashes.
- **Validation**: Contact requests validate email exists, not self, no duplicates, not blocked. Messages validate contact is accepted and not blocked/ignored/trashed.
- **CSRF**: Laravel's default CSRF protection on all forms.
- **Rate limiting**: Applied to message sends and contact requests to prevent spam.
- **Cascade on user delete**: When a user deletes their account, all related contacts, blocks, ignores, trashes, conversations, and messages are cascade-deleted via foreign keys.

---

## Scheduled Jobs

| Command | Schedule | Action |
|---------|----------|--------|
| `CleanExpiredIgnores` | Every minute | Deletes ignore records where `expires_at < now()` |
| `CleanExpiredTrashes` | Every minute | For expired trashes: hard deletes Contact + Conversation + Messages for both users, then deletes Trash record |

Registered in `routes/console.php`.

---

## Testing Strategy

PHPUnit feature tests for each feature area:

### Contact Tests
- Send contact request (happy path)
- Accept contact request (creates conversation)
- Decline contact request (deletes record)
- Cannot request self
- Cannot send duplicate request
- Cannot request blocked user
- Cannot request user who blocked you

### Block Tests
- Block a contact (cascading deletes contact, conversation, messages, trash, ignore)
- Block from pending request
- Unblock allows new request
- Cannot block non-contact
- Blocked user sees blocked indicator

### Ignore Tests
- Ignore with preset duration
- Ignore with custom date
- Conversation hidden for ignorer
- Ignored user cannot send messages
- Auto-expiry via scheduled command
- Ignore visibility to ignored user

### Trash Tests
- Normal trash with preset period
- Normal trash with custom date
- Quick delete wipes messages immediately
- Restore from trash (messages visible again)
- Restore from quick delete (conversation empty)
- Auto-expiry hard deletes for both users
- Other user can still send during trash period

### Conversation & Message Tests
- Conversation created on contact accept
- Send encrypted message
- Receive and decrypt message
- Cannot message blocked contact
- Cannot message ignored-by contact
- Cannot message trashed contact
- Polling returns new messages

### Edge Case Tests
- Block while contact is trashed (block supersedes)
- Ignore then trash same contact
- Mutual operations (both users act simultaneously)
- User account deletion cascading
