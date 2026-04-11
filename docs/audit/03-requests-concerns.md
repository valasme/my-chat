# Audit: Form Requests & Validation Concerns

## Files Covered
- `app/Http/Requests/StoreContactRequest.php`
- `app/Http/Requests/UpdateContactRequest.php`
- `app/Http/Requests/StoreMessageRequest.php`
- `app/Http/Requests/StoreBlockRequest.php`
- `app/Http/Requests/StoreIgnoreRequest.php`
- `app/Http/Requests/StoreTrashRequest.php`
- `app/Concerns/BlockValidationRules.php`
- `app/Concerns/ContactValidationRules.php`
- `app/Concerns/IgnoreValidationRules.php`
- `app/Concerns/MessageValidationRules.php`
- `app/Concerns/TrashValidationRules.php`

---

## 🔴 CRITICAL — Security

### S7: StoreMessageRequest — Double Query for Block Check
**File:** `StoreMessageRequest.php`
**Severity:** Medium
**Issue:** The `after()` closure calls `User::find($otherUserId)` TWICE:
```php
if ($this->user()->hasBlockedUser(User::find($otherUserId))
    || $this->user()->isBlockedByUser(User::find($otherUserId))) {
```
Each `User::find()` is a separate query, and each `hasBlockedUser`/`isBlockedByUser` is another query. That's 4 queries just for the block check.
**Fix:** Load the other user once:
```php
$otherUser = User::find($otherUserId);
if (!$otherUser || $this->user()->hasBlockedUser($otherUser) || $this->user()->isBlockedByUser($otherUser)) {
```

### S8: StoreMessageRequest — No Null Check on User::find
**File:** `StoreMessageRequest.php`
**Severity:** Medium
**Issue:** `User::find($otherUserId)` could return `null` if the other user is deleted. Passing `null` to `hasBlockedUser()` would throw an error.
**Fix:** Add null check before block/ignore checks.

### S9: StoreBlockRequest — Allows Blocking Users Without Contact (Inconsistency)
**File:** `StoreBlockRequest.php`
**Severity:** Low
**Issue:** The `after()` validation checks `Contact::between(...)->exists()` and rejects if there's no contact relationship. However, the validation message says "You can only block users you have a contact relationship with" — but the original spec says blocking should cascade-delete everything. If there's no contact, there's nothing to cascade. The check is correct for preventing orphan blocks, but the error message could be clearer.
**Fix:** Clarify intent — is blocking without a contact intentionally blocked? If users should be able to block strangers (e.g., from a pending request), remove the contact requirement.

### S10: StoreTrashRequest — `is_quick_delete` Not Validated in `after()`
**File:** `StoreTrashRequest.php`
**Severity:** Low
**Issue:** The `after()` closure doesn't check `is_quick_delete` — it only validates the contact. The `is_quick_delete` flag bypasses the duration validation entirely because of `required_without:is_quick_delete` on the `duration` field. A user could send `is_quick_delete=true` with any contact to immediately wipe all messages.
**Fix:** Add explicit check in `after()` that confirms the user intends quick-delete (this is somewhat mitigated by the UI confirm dialog, but server-side validation is better).

---

## 🟡 MEDIUM — Error Handling

### E4: UpdateContactRequest::authorize — Direct Property Access Without Null Check
**File:** `UpdateContactRequest.php`
**Severity:** Low
**Issue:** `$this->route('contact')` could theoretically return null (if route model binding fails). The authorize method accesses `->contact_user_id` and `->status` directly.
**Fix:** Laravel's route model binding will 404 before this, so this is safe in practice. No action needed.

### E5: StoreIgnoreRequest — Duplicate Active Ignore Check Timing
**File:** `StoreIgnoreRequest.php`
**Severity:** Low
**Issue:** The `after()` check for duplicate ignores uses `->active()->exists()`. If a user has an expired ignore for the same user, they can create a new one — but the old record still exists in the database. This is fine functionally, but means the `unique` constraint on `[ignorer_id, ignored_id]` would fail.
**Fix:** Either:
1. Use `upsert` in the controller to overwrite expired ignores, or
2. Check for ANY ignore (not just active) and provide "already exists" or "extend" flow

---

## 🟡 MEDIUM — Code Quality

### D6: Validation Concerns Are Thin Wrappers
**Files:** All 5 concern files
**Severity:** Low
**Issue:** Each validation concern trait contains a single method returning an array. With only one consumer each, these could be inline. The traits add indirection without reuse benefit.
**Fix:** Consider inlining rules in the FormRequest classes, or keep traits if planning future reuse (e.g., API controllers).

### D7: `after()` Closures Contain Business Logic
**Files:** All FormRequests with `after()`
**Severity:** Medium
**Issue:** The `after()` closures perform complex business logic (checking blocks, contacts, ignores, trash). This logic is also partially duplicated in Policies. For example:
- `StoreMessageRequest::after()` checks blocks, ignores, contacts, and trash
- `MessagePolicy::create()` checks the exact same conditions
Both run on every message send, doubling the query load.
**Fix:** Consolidate — either use FormRequest OR Policy for business rules, not both. If using FormRequest for user-facing error messages and Policy for authorization, consider having the FormRequest call the Policy internally.

### D8: Inconsistent authorize() Pattern
**Files:** Various FormRequests
**Severity:** Low
**Issue:**
- `StoreContactRequest`, `StoreMessageRequest`, `StoreBlockRequest`, `StoreIgnoreRequest`, `StoreTrashRequest` all return `true` from `authorize()` — they rely on middleware + `after()` for authorization.
- `UpdateContactRequest` does actual authorization in `authorize()`.
This inconsistency means authorization is split between FormRequests, Policies, and controller Gates.
**Fix:** Standardize: use `authorize()` in FormRequest for ownership checks, Gates/Policies for resource authorization.

---

## 🔵 LOW — Documentation

### DOC2: No Docblocks on Validation Rules
**Files:** All concern traits
**Severity:** Low
**Issue:** No documentation on what each rule means, max lengths, or why specific values were chosen (e.g., why 5000 char max for messages, why those specific ignore durations).
**Fix:** Add inline comments explaining business decisions.

---

## Improvement Summary

| ID | Severity | Category | Description |
|----|----------|----------|-------------|
| S7 | 🟡 Medium | Security/Perf | Double User::find in message validation |
| S8 | 🟡 Medium | Security | No null check on User::find |
| S9 | 🔵 Low | Security | Block requires contact relationship |
| S10 | 🔵 Low | Security | Quick-delete not validated in after() |
| E4 | 🔵 Low | Error Handling | Direct property access without null |
| E5 | 🔵 Low | Error Handling | Duplicate ignore check vs unique constraint |
| D6 | 🔵 Low | Code Quality | Thin validation concern wrappers |
| D7 | 🟡 Medium | Code Quality | Business logic duplicated in FormRequest + Policy |
| D8 | 🔵 Low | Code Quality | Inconsistent authorize() pattern |
| DOC2 | 🔵 Low | Documentation | No validation rule docblocks |
