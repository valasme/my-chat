# Audit: Policies

## Files Covered
- `app/Policies/ContactPolicy.php`
- `app/Policies/ConversationPolicy.php`
- `app/Policies/MessagePolicy.php`
- `app/Policies/BlockPolicy.php`
- `app/Policies/IgnorePolicy.php`
- `app/Policies/TrashPolicy.php`

---

## 🟠 HIGH — Security

### S11: MessagePolicy::create — Not Used by MessageController
**File:** `MessagePolicy.php`, `MessageController.php`
**Severity:** High
**Issue:** `MessagePolicy::create()` contains comprehensive authorization logic (checks participant, blocks, ignores, contacts, trash) but the `MessageController::store()` method only calls:
```php
Gate::authorize('view', $conversation);
```
It authorizes with `ConversationPolicy::view()` (participant check only), NOT `MessagePolicy::create()`. The comprehensive checks exist ONLY in `StoreMessageRequest::after()`.

This means the Policy is dead code — it runs zero times. If someone bypasses the FormRequest (e.g., via API, or a different controller), the policy won't protect.
**Fix:** Use the policy in the controller:
```php
Gate::authorize('create', [Message::class, $conversation]);
```
Then remove duplicate logic from either Policy or FormRequest.

### S12: ConversationPolicy — No Check for Blocked/Ignored Users
**File:** `ConversationPolicy.php`
**Severity:** Medium
**Issue:** `ConversationPolicy::view()` only checks `hasParticipant()`. A blocked user can still view the conversation they previously participated in. While the message sending is blocked by FormRequest, viewing the conversation (and all its messages) is allowed.
**Fix:** Add block/ignore checks to the view policy if conversations should be hidden after blocking.

### S13: Policies Return `true` for viewAny/create Without Context
**Files:** All policies
**Severity:** Low
**Issue:** Methods like `viewAny()` and `create()` unconditionally return `true`. While auth middleware protects unauthenticated access, these policy methods provide no additional protection.
**Fix:** This is fine if middleware handles auth. Consider removing unnecessary policy methods (Laravel auto-allows if method doesn't exist on policy).

---

## 🟡 MEDIUM — Code Quality

### D9: MessagePolicy::create Has Complex Logic That Should Be in a Service
**File:** `MessagePolicy.php`
**Severity:** Medium
**Issue:** The `create` method contains 5 different checks with 3 database queries. This is heavy for a policy. It also duplicates `StoreMessageRequest::after()` logic exactly.
**Fix:** Extract into a `CanSendMessage` service or action class, used by both Policy and FormRequest.

### D10: No Policy for DashboardController
**File:** N/A
**Severity:** Low
**Issue:** Dashboard has no policy. It relies solely on `auth` middleware. While this is fine (dashboard is user-specific), for consistency with the rest of the app, a policy could be useful.
**Fix:** Not needed — `auth` middleware is sufficient for the dashboard.

---

## 🔵 LOW — Documentation

### DOC3: Policies Lack Docblocks
**Files:** All policies except ContactPolicy
**Severity:** Low
**Issue:** `ConversationPolicy`, `MessagePolicy`, `BlockPolicy`, `IgnorePolicy`, `TrashPolicy` have no docblocks explaining the authorization logic.
**Fix:** Add docblocks explaining what each check does.

---

## Improvement Summary

| ID | Severity | Category | Description |
|----|----------|----------|-------------|
| S11 | 🟠 High | Security | MessagePolicy::create is dead code |
| S12 | 🟡 Medium | Security | ConversationPolicy allows blocked users to view |
| S13 | 🔵 Low | Security | Policies return true unconditionally |
| D9 | 🟡 Medium | Code Quality | Complex logic in policy should be in service |
| D10 | 🔵 Low | Code Quality | No dashboard policy needed |
| DOC3 | 🔵 Low | Documentation | Policies lack docblocks |
