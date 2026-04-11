# Audit: Models

## Files Covered
- `app/Models/User.php`
- `app/Models/Contact.php`
- `app/Models/Conversation.php`
- `app/Models/Message.php`
- `app/Models/Block.php`
- `app/Models/Ignore.php`
- `app/Models/Trash.php`

---

## 🔴 CRITICAL — Security

### S5: Message Encryption Key Management
**File:** `Message.php`
**Severity:** High
**Issue:** Message bodies use Laravel's `encrypted` cast which relies on `APP_KEY`. If `APP_KEY` is rotated or lost, ALL message history becomes unreadable. There's no key rotation strategy. For a "secure, private" chat app, this is a critical concern.
**Fix:**
1. Document that `APP_KEY` must NEVER be rotated without a migration strategy
2. Consider implementing envelope encryption (per-conversation keys encrypted with master key)
3. Add a command to re-encrypt messages if key rotation is ever needed
4. Store encrypted key backups securely

### S6: User Model Exposes Email in Relationships
**File:** `User.php`
**Severity:** Low
**Issue:** The `#[Hidden]` attribute hides password/tokens from serialization, but `email` is not hidden. When User models are loaded via relationships (eager loading), the email is accessible. For a privacy-focused app, consider whether emails should be visible to other users.
**Fix:** Evaluate if contact users should see each other's emails. If not, use a DTO or API resource to filter fields.

---

## 🟠 HIGH — Performance

### P5: User::contacts() Method Returns Full Collection
**File:** `User.php`
**Severity:** Medium
**Issue:** The `contacts()` method calls `->get()` directly, returning a full Collection. This is not a standard Eloquent relationship — it cannot be used with eager loading, `whereHas`, or query chaining. Anyone calling `$user->contacts()` gets all contacts loaded into memory.
**Fix:** Convert to a proper scope or relationship pattern, or rename to `getContacts()` to signal it's not a relationship.

### P6: User Helper Methods Query Every Time
**File:** `User.php`
**Severity:** Medium
**Issue:** Methods like `isContactOf()`, `hasPendingContactWith()`, `hasBlockedUser()`, `isIgnoringUser()` each run a database query. If called in a loop (e.g., rendering a contact list with status checks), this creates N+1 problems.
**Fix:** Consider caching results per-request or pre-loading relationship data.

---

## 🟡 MEDIUM — Bugs / Logic Issues

### B6: Conversation::scopeForUser — Missing Query Group
**File:** `Conversation.php`
**Severity:** High (affects query correctness)
**Issue:** The scope uses bare `orWhere`:
```php
public function scopeForUser($query, int $userId)
{
    return $query->where('user_one_id', $userId)
        ->orWhere('user_two_id', $userId);
}
```
When this scope is combined with other `where()` clauses (as it is in `ConversationController::index`), the `orWhere` breaks SQL grouping. The generated SQL becomes:
```sql
WHERE user_one_id = ? OR user_two_id = ? AND other_conditions...
```
Instead of:
```sql
WHERE (user_one_id = ? OR user_two_id = ?) AND other_conditions...
```
This means the `->when($excludeUserIds...)` conditions in ConversationController only apply to the `user_two_id` branch, not `user_one_id`.
**Fix:** Wrap in a closure:
```php
public function scopeForUser($query, int $userId)
{
    return $query->where(function ($q) use ($userId) {
        $q->where('user_one_id', $userId)->orWhere('user_two_id', $userId);
    });
}
```

### B7: Ignore::scopeActive — No Index Support for Performance
**File:** `Ignore.php`
**Severity:** Low
**Issue:** `scopeActive()` filters by `expires_at > now()`. The `ignores` table has a unique index on `[ignorer_id, ignored_id]` but no index on `expires_at`. Active ignore lookups (used on every message send and conversation list) scan without index help.
**Fix:** Add a composite index: `[ignorer_id, expires_at]` or `[ignored_id, expires_at]`.

### B8: Contact::scopeBetween — Overly Nested Where Groups
**File:** `Contact.php`
**Severity:** Low
**Issue:** The scope has 3 levels of nesting:
```php
$query->where(function (Builder $q) use ($userA, $userB) {
    $q->where(function (Builder $q) use ($userA, $userB) { ... })
      ->orWhere(function (Builder $q) use ($userA, $userB) { ... });
});
```
The outer `where()` wrapper is redundant — the inner two `where/orWhere` already form a group.
**Fix:** Remove the outer wrapper.

---

## 🟡 MEDIUM — Code Quality

### D3: Missing Return Type Hints on Scopes
**Files:** `Conversation.php`, `Block.php`, `Ignore.php`, `Trash.php`
**Severity:** Low
**Issue:** Multiple scope methods lack `Builder` parameter type hints and `void` return types:
```php
// Current
public function scopeForUser($query, int $userId)

// Should be
public function scopeForUser(Builder $query, int $userId): void
```
This affects IDE autocomplete, static analysis, and documentation.
**Fix:** Add type hints to all scope methods.

### D4: Inconsistent Scope Return Patterns
**Files:** Multiple models
**Severity:** Low
**Issue:** Some scopes `return $query->...` while others use `void` pattern (`$query->where(...)` without return). Contact model uses `void`, others use `return`. Both work in Laravel, but should be consistent.
**Fix:** Pick one pattern (prefer `void` for Laravel 13+) and apply consistently.

### D5: No PHPDoc on Model Properties
**Files:** All models
**Severity:** Low
**Issue:** No `@property` PHPDoc annotations on any model. IDE users get no autocomplete for `$contact->status`, `$message->body`, etc.
**Fix:** Add `@property` annotations or use a tool like `ide-helper`.

---

## 🔵 LOW — Documentation

### DOC1: No Model Documentation
**Files:** All models
**Severity:** Low
**Issue:** No class-level docblocks explaining:
- What the model represents
- Business rules (e.g., contacts are symmetric, conversations use canonical ordering)
- Relationship constraints
**Fix:** Add docblocks to each model.

---

## Improvement Summary

| ID | Severity | Category | Description |
|----|----------|----------|-------------|
| S5 | 🟠 High | Security | No encryption key rotation strategy |
| S6 | 🔵 Low | Security | Emails visible through relationships |
| P5 | 🟡 Medium | Performance | contacts() returns full collection |
| P6 | 🟡 Medium | Performance | Helper methods query every call |
| B6 | 🟠 High | Bug | scopeForUser missing query group |
| B7 | 🔵 Low | Bug | No index on expires_at for active scope |
| B8 | 🔵 Low | Bug | Overly nested where groups |
| D3 | 🔵 Low | Code Quality | Missing type hints on scopes |
| D4 | 🔵 Low | Code Quality | Inconsistent scope patterns |
| D5 | 🔵 Low | Code Quality | No @property PHPDoc |
| DOC1 | 🔵 Low | Documentation | No model docblocks |
