# Audit: Controllers

## Files Covered
- `app/Http/Controllers/ContactController.php`
- `app/Http/Controllers/ConversationController.php`
- `app/Http/Controllers/MessageController.php`
- `app/Http/Controllers/BlockController.php`
- `app/Http/Controllers/IgnoreController.php`
- `app/Http/Controllers/TrashController.php`
- `app/Http/Controllers/DashboardController.php`

---

## 🔴 CRITICAL — Security

### S1: No Rate Limiting on Any Route
**Files:** All controllers / `routes/web.php`
**Severity:** Critical
**Issue:** No rate limiting middleware applied to any route. Users can:
- Spam contact requests endlessly
- Send unlimited messages per second
- Trigger unlimited block/ignore/trash operations
- Hammer the dashboard (expensive queries)
**Fix:** Add `throttle` middleware to the route group:
```php
Route::middleware(['auth', 'throttle:60,1'])->group(function () { ... });
```
Consider stricter limits for write operations:
```php
Route::post('contacts', ...)->middleware('throttle:10,1');  // 10 requests/minute
Route::post('conversations/{conversation}/messages', ...)->middleware('throttle:30,1');
```

### S2: BlockController::store — No Authorization Gate
**File:** `BlockController.php`
**Severity:** High
**Issue:** The `store` method relies entirely on `StoreBlockRequest` validation. There is no `Gate::authorize()` call. The FormRequest `after()` method checks for existing relationships, but a user could potentially craft requests to bypass. The block action performs cascading deletes (contacts, conversations, messages, ignores, trash) — this destructive operation should have a policy gate.
**Fix:** Add a `create` policy check or explicit Gate authorization.

### S3: IgnoreController::store / TrashController::store — No Authorization Gate
**Files:** `IgnoreController.php`, `TrashController.php`
**Severity:** Medium
**Issue:** Same pattern as BlockController — no explicit Gate authorization on store actions. FormRequest `after()` closures are the only protection.
**Fix:** Add policy-based authorization even if FormRequest handles validation.

### S4: TrashController::store — `is_quick_delete` Comes From User Input
**File:** `TrashController.php`
**Severity:** Medium
**Issue:** `is_quick_delete` is read from request input and is truthy-checked. While validated as `nullable|boolean`, a malicious user could send `is_quick_delete=1` on any trash store request. The field is also used as a `name` attribute on a submit button in the view, meaning it gets submitted as a form field — this is correct behavior but worth noting that the "quick delete" path deletes all messages immediately.
**Fix:** Ensure `is_quick_delete` has proper validation + add a confirmation step server-side or at minimum log the action.

---

## 🟠 HIGH — Performance

### P1: DashboardController — Duplicated Conversation Exclusion Logic (12+ Queries)
**File:** `DashboardController.php`
**Severity:** High
**Issue:** The dashboard runs the full conversation-exclusion logic twice (once for count, once for recent list). This means:
1. `Ignore::forIgnorer()->active()->pluck()` — 1 query
2. `Trash::forUser()->pluck()` — 1 query
3. `Contact::whereIn()->get()->map()` — 1 query + PHP processing
4. Conversations count query — 1 query
5. Conversations list query with subquery ordering — 1 query
6. Incoming requests query + count — 2 queries
7. Expiring ignores query — 1 query
8. Expiring trashes query with eager loads — 1+ queries

Total: ~12-15 queries per dashboard load.
**Fix:** Extract the exclude-users logic into a shared scope or service class. Cache the `$excludeUserIds` collection. Consider using `DB::raw()` subqueries instead of loading Contact models to map IDs.

### P2: ConversationController::index — N+1 on Trashy Users Mapping
**File:** `ConversationController.php`
**Severity:** Medium
**Issue:** `Contact::whereIn('id', $trashedContactIds)->get()->map(...)` loads full Contact models just to extract user IDs. This could be a simple pluck + SQL.
**Fix:** Replace with a subquery:
```php
$trashedUserIds = Contact::whereIn('id', $trashedContactIds)
    ->selectRaw("CASE WHEN user_id = ? THEN contact_user_id ELSE user_id END as other_id", [$userId])
    ->pluck('other_id');
```

### P3: ConversationController::show — All Messages Loaded at Once
**File:** `ConversationController.php`
**Severity:** Medium
**Issue:** `$conversation->messages()->oldest()->get()` loads ALL messages. For conversations with 100+ messages, this could be slow and memory-intensive. Combined with the `encrypted` cast (each message is decrypted on load), this is expensive.
**Fix:** Paginate messages or use cursor-based loading (load last 50, scroll to load more).

### P4: CleanExpiredTrashes — Loads Models One-by-One
**File:** `CleanExpiredTrashes.php`
**Severity:** Low
**Issue:** Uses `chunkById` which is good, but inside the loop it:
1. Accesses `$trash->contact` (already eager-loaded ✓)
2. Runs `Conversation::betweenUsers()->first()` per trash — 1 query per item
3. Deletes messages, conversation, contact, trash — 4 operations per item
**Fix:** For bulk cleanup, consider using raw SQL deletes with joins. Or at minimum batch the conversation lookups.

---

## 🟡 MEDIUM — Error Handling

### E1: ContactController::store — `firstOrFail()` Without Try/Catch
**File:** `ContactController.php`
**Severity:** Medium
**Issue:** `User::where('email', ...)->firstOrFail()` will throw `ModelNotFoundException` if the user doesn't exist after validation passes. While the `StoreContactRequest` validates `exists:users,email`, there's a race condition window between validation and the `firstOrFail` call (user deleted between).
**Fix:** Use `first()` with a null check, or wrap in try/catch.

### E2: TrashController::store — `findOrFail` Race Condition
**File:** `TrashController.php`
**Severity:** Low
**Issue:** `Contact::findOrFail($request->validated('contact_id'))` can throw if the contact is deleted between validation and execution (e.g., the other user deletes the contact simultaneously).
**Fix:** Use `find()` with graceful redirect on null.

### E3: No Global Exception Handling Configuration
**File:** `bootstrap/app.php`
**Severity:** Medium
**Issue:** The `withExceptions` callback is empty. In production, you should:
- Report specific exceptions to monitoring (Sentry, etc.)
- Customize error views for common HTTP errors
- Suppress sensitive info in error responses
**Fix:** Configure exception handling for production.

---

## 🟡 MEDIUM — Bugs / Logic Issues

### B1: ConversationController::show — Missing Check for Blocked/Ignored/Trashed State
**File:** `ConversationController.php`
**Severity:** Medium
**Issue:** The `show` method checks if the OTHER user has ignored the current user (`$isIgnored`) and if the contact is trashed, but does NOT check:
1. If the current user has BLOCKED the other user (they can still view old conversations)
2. If the current user is IGNORING the other user (they can still view but no exclusion)
This isn't necessarily a bug (viewing old conversations may be intended), but the conversation index excludes these while the show page allows access.
**Fix:** Decide policy: should blocked/ignored conversations be viewable? If not, add checks.

### B2: BlockController::store — Race Condition on Cascading Deletes
**File:** `BlockController.php`
**Severity:** Medium
**Issue:** The block store does cascading deletes (ignore → trash → conversation+messages → contact) without a database transaction. If the request fails midway, data could be partially deleted.
**Fix:** Wrap in `DB::transaction()`:
```php
DB::transaction(function () use ($userId, $targetUserId) {
    // all deletes + block create
});
```

### B3: TrashController::forceDelete — No Transaction
**File:** `TrashController.php`
**Severity:** Medium
**Issue:** Same as B2 — cascading deletes without transaction protection.
**Fix:** Wrap in `DB::transaction()`.

### B4: ContactController::destroy — No Transaction
**File:** `ContactController.php`
**Severity:** Medium
**Issue:** Deletes conversation, messages, then contact without a transaction.
**Fix:** Wrap in `DB::transaction()`.

### B5: Conversation::scopeForUser — Incorrect Without Group
**File:** `ConversationController.php` (using the scope)
**Severity:** Low
**Issue:** `Conversation::scopeForUser` uses `->orWhere()` which can interact poorly with other `where` clauses in the chain. In `ConversationController::index`, it's used with `.when()` for excluding users, and the `orWhere` could lead to unexpected results.
The scope is:
```php
$query->where('user_one_id', $userId)->orWhere('user_two_id', $userId);
```
When chained with additional `where()` clauses, the `orWhere` breaks the AND grouping.
**Fix:** Wrap in a `where(function() { ... })` group:
```php
$query->where(function ($q) use ($userId) {
    $q->where('user_one_id', $userId)->orWhere('user_two_id', $userId);
});
```

---

## 🔵 LOW — Production Readiness

### PR1: Schedule Commands Run Every Minute
**File:** `routes/console.php`
**Severity:** Low
**Issue:** Both `clean-expired-ignores` and `clean-expired-trashes` run `everyMinute()`. For most apps, `hourly()` or `everyFiveMinutes()` would be sufficient and reduce database load.
**Fix:** Change to `everyFiveMinutes()` or `hourly()`.

### PR2: No Logging in Controllers
**Files:** All controllers
**Severity:** Low
**Issue:** No logging for important actions like blocking, deleting contacts, or quick-deleting messages. For a "secure" app, audit logging is important.
**Fix:** Add `Log::info()` for destructive actions (block, delete, force-delete, quick-delete).

### PR3: Flash Messages Not Escaped
**Files:** All controllers
**Severity:** Low
**Issue:** Flash messages use `__()` for translation which is fine, but the views display them inside `<flux:callout>{{ session('status') }}</flux:callout>` — Blade's `{{ }}` auto-escapes, so this is safe. No action needed.

---

## 🔵 LOW — Code Quality / DRY

### D1: Conversation Exclusion Logic Duplicated
**Files:** `ConversationController.php`, `DashboardController.php`
**Severity:** Medium
**Issue:** The complex query logic for excluding ignored/trashed users from conversations is copy-pasted between ConversationController and DashboardController.
**Fix:** Extract into a `ConversationQueryService` or add a scope to the Conversation model.

### D2: Missing Type Hints on Scope Methods
**Files:** `Conversation.php`, `Block.php`, `Ignore.php`, `Trash.php`
**Severity:** Low
**Issue:** Several scope methods lack explicit `Builder` type hints on `$query` parameter and `void` return types.
**Fix:** Add proper PHPDoc and type hints.

---

## Improvement Summary

| ID | Severity | Category | Description |
|----|----------|----------|-------------|
| S1 | 🔴 Critical | Security | No rate limiting on any route |
| S2 | 🟠 High | Security | No Gate authorization on block store |
| S3 | 🟡 Medium | Security | No Gate authorization on ignore/trash store |
| S4 | 🟡 Medium | Security | Quick-delete via user input |
| P1 | 🟠 High | Performance | Dashboard runs 12+ queries |
| P2 | 🟡 Medium | Performance | N+1 on trashed user ID mapping |
| P3 | 🟡 Medium | Performance | All messages loaded at once |
| P4 | 🔵 Low | Performance | Cleanup command per-item queries |
| E1 | 🟡 Medium | Error Handling | firstOrFail race condition |
| E2 | 🔵 Low | Error Handling | findOrFail race condition |
| E3 | 🟡 Medium | Error Handling | No exception handler config |
| B1 | 🟡 Medium | Bug | Show page missing block/ignore checks |
| B2 | 🟡 Medium | Bug | Block cascade no transaction |
| B3 | 🟡 Medium | Bug | Force-delete no transaction |
| B4 | 🟡 Medium | Bug | Contact delete no transaction |
| B5 | 🔵 Low | Bug | scopeForUser orWhere grouping |
| PR1 | 🔵 Low | Production | Cleanup every minute too frequent |
| PR2 | 🔵 Low | Production | No audit logging |
| D1 | 🟡 Medium | DRY | Conversation exclusion duplicated |
| D2 | 🔵 Low | Code Quality | Missing type hints on scopes |
