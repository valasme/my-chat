# Audit: Migrations & Database Schema

## Files Covered
- `database/migrations/2026_04_11_172736_create_contacts_table.php`
- `database/migrations/2026_04_11_182246_create_conversations_table.php`
- `database/migrations/2026_04_11_182347_create_blocks_table.php`
- `database/migrations/2026_04_11_182348_create_ignores_table.php`
- `database/migrations/2026_04_11_182348_create_messages_table.php`
- `database/migrations/2026_04_11_182348_create_trashes_table.php`

---

## 🟠 HIGH — Performance

### P7: Messages Table — No Index on conversation_id + created_at
**File:** `create_messages_table.php`
**Severity:** High
**Issue:** The messages table has only a `conversation_id` foreign key index. The most common query pattern is:
```sql
SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 1
```
(Used in ConversationController and DashboardController for "last message" subquery)
Without a composite index on `[conversation_id, created_at]`, this requires a filesort for every conversation.
**Fix:** Add migration:
```php
$table->index(['conversation_id', 'created_at']);
```

### P8: Messages Table — No Index on sender_id
**File:** `create_messages_table.php`
**Severity:** Low
**Issue:** While there's a foreign key on `sender_id` (which creates an index), if messages are ever queried by sender (e.g., "my messages"), the foreign key index may not be optimal.
**Fix:** Already covered by FK index — no action needed.

### P9: Contacts Table — Consider Index on [user_id, status]
**File:** `create_contacts_table.php`
**Severity:** Medium
**Issue:** The most common contact query is `forUser($userId)->accepted()` which filters by `(user_id = ? OR contact_user_id = ?) AND status = 'accepted'`. The existing indexes are:
- `unique [user_id, contact_user_id]`
- `[contact_user_id, status]`
Missing: `[user_id, status]` composite index for the `user_id` branch of the OR query.
**Fix:** Add `$table->index(['user_id', 'status']);`

### P10: Ignores Table — No Index on expires_at
**File:** `create_ignores_table.php`
**Severity:** Medium
**Issue:** Active ignore checks filter by `expires_at > now()`. The unique index `[ignorer_id, ignored_id]` doesn't help with the `expires_at` filter. Both the cleanup command and active scope need this.
**Fix:** Add `$table->index(['ignorer_id', 'expires_at']);` and `$table->index(['ignored_id', 'expires_at']);`

### P11: Trashes Table — No Index on expires_at
**File:** `create_trashes_table.php`
**Severity:** Medium
**Issue:** The cleanup command queries `Trash::expired()` which filters by `expires_at <= now()`. No index on `expires_at`.
**Fix:** Add `$table->index('expires_at');`

---

## 🟡 MEDIUM — Schema Design

### SD1: Contacts status Column — No Enum/Check Constraint
**File:** `create_contacts_table.php`
**Severity:** Low
**Issue:** `status` is a bare `string` column. Nothing prevents invalid values like `'foo'` from being inserted at the database level. Validation only happens at the application level.
**Fix:** Either use an enum column or add a CHECK constraint:
```php
$table->enum('status', ['pending', 'accepted']);
```

### SD2: Conversations — No Index for "Other User" Lookups
**File:** `create_conversations_table.php`
**Severity:** Low
**Issue:** The `scopeForUser` query uses `user_one_id = ? OR user_two_id = ?`. With the unique index on `[user_one_id, user_two_id]`, the `user_one_id` branch is covered but `user_two_id` alone is not.
**Fix:** Add `$table->index('user_two_id');` (foreign key may already create this depending on the DB driver).

### SD3: Blocks — No Reverse Index
**File:** `create_blocks_table.php`
**Severity:** Low
**Issue:** The unique index `[blocker_id, blocked_id]` covers "who did I block?" but not "who blocked me?" efficiently. The `isBlockedByUser()` check queries by `blocked_id` first.
**Fix:** Add `$table->index('blocked_id');`

---

## 🔵 LOW — Production Readiness

### PR4: No `down()` Safety for Data Tables
**Files:** All migrations
**Severity:** Low
**Issue:** All `down()` methods use `Schema::dropIfExists()`. In production, rolling back a migration would silently destroy all data with no warning or backup.
**Fix:** Consider throwing an exception in `down()` for production:
```php
public function down(): void
{
    if (app()->isProduction()) {
        throw new \RuntimeException('Cannot rollback in production');
    }
    Schema::dropIfExists('contacts');
}
```

---

## Improvement Summary

| ID | Severity | Category | Description |
|----|----------|----------|-------------|
| P7 | 🟠 High | Performance | Messages: no composite index for last-message query |
| P8 | 🔵 Low | Performance | Messages: sender_id covered by FK |
| P9 | 🟡 Medium | Performance | Contacts: missing [user_id, status] index |
| P10 | 🟡 Medium | Performance | Ignores: no index on expires_at |
| P11 | 🟡 Medium | Performance | Trashes: no index on expires_at |
| SD1 | 🔵 Low | Schema | No enum constraint on contact status |
| SD2 | 🔵 Low | Schema | No index on user_two_id |
| SD3 | 🔵 Low | Schema | No reverse index on blocked_id |
| PR4 | 🔵 Low | Production | Rollback drops data without warning |
