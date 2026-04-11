# Audit: Tests

## Files Covered
- `tests/Feature/ContactTest.php` (20 tests)
- `tests/Feature/ConversationTest.php`
- `tests/Feature/MessageTest.php` (6 tests)
- `tests/Feature/BlockTest.php`
- `tests/Feature/IgnoreTest.php`
- `tests/Feature/TrashTest.php`
- `tests/Feature/DashboardTest.php` (9 tests)
- `tests/Feature/IntegrationTest.php` (4 tests)

**Total: 105 tests, 255 assertions — all passing**

---

## 🟡 MEDIUM — Missing Test Coverage

### T1: No Tests for Rate Limiting (When Added)
**Severity:** Medium
**Issue:** When rate limiting is added (per S1/S14), tests should verify that:
- Exceeding the limit returns 429
- Different endpoints have appropriate limits
- Rate limits reset after the window

### T2: No Tests for Concurrent/Race Condition Scenarios
**Severity:** Medium
**Issue:** Several bugs involve race conditions (B2, B3, B4, PR7), but no tests verify:
- Simultaneous block + message send
- Simultaneous contact delete from both users
- Trash cleanup while user is restoring
**Fix:** Add tests using `DB::transaction` assertions or concurrent request simulation.

### T3: No Tests for Message Encryption Persistence
**Severity:** Low
**Issue:** `MessageTest::test_message_body_is_encrypted_in_database` verifies encryption works, but doesn't test:
- Messages are readable after APP_KEY change (they shouldn't be — this should be documented)
- Very long encrypted messages
- Special characters in encrypted bodies
**Fix:** Add edge case tests.

### T4: No Tests for Scheduled Commands
**Severity:** Medium
**Issue:** `CleanExpiredIgnores` and `CleanExpiredTrashes` have no dedicated test files. The integration test covers some cascading, but doesn't test:
- Command with no expired records
- Command with multiple expired records
- Partial cleanup on error
- Trash cleanup cascading deletes
**Fix:** Add `tests/Feature/CleanExpiredIgnoresTest.php` and `tests/Feature/CleanExpiredTrashesTest.php`.

### T5: No Tests for Conversation Exclusion Logic
**Severity:** Medium
**Issue:** The complex conversation-exclusion logic (ignore/trash filtering) in `ConversationController::index` has no direct test. The DashboardTest tests it indirectly, but there's no test that:
- Verifies ignored user conversations don't appear in the list
- Verifies trashed contact conversations don't appear
- Verifies the exclusion is per-user (other user still sees it)
**Fix:** Add tests in `ConversationTest.php`.

### T6: No Edge Case Tests for Block Cascade
**Severity:** Low
**Issue:** `IntegrationTest::test_block_while_trashed_cleans_everything` is good but doesn't test:
- Block when there's no contact (should fail validation)
- Block with multiple conversations (impossible in current schema — 1:1 only)
- Block cleans up ignore from BOTH directions
**Fix:** Add additional block cascade tests.

### T7: No Tests for the `forceDelete` Trash Flow
**Severity:** Medium
**Issue:** The `TrashTest` file likely tests force-delete, but there's no test verifying:
- Force-delete when conversation has already been deleted
- Force-delete when contact has already been deleted (cascade from other user)
- Authorization: other users can't force-delete your trash
**Fix:** Verify existing coverage and add edge cases.

---

## 🟡 MEDIUM — Test Quality

### TQ1: Tests Don't Assert Flash Messages
**Files:** Most test files
**Severity:** Low
**Issue:** Tests assert redirects but don't verify the flash message content:
```php
->assertRedirect(route('contacts.index'));
// Missing: ->assertSessionHas('status', 'Contact request sent.');
```
**Fix:** Add `assertSessionHas('status', ...)` to verify correct feedback.

### TQ2: Tests Don't Assert Database State After Failures
**Files:** Some test files
**Severity:** Low
**Issue:** Some tests assert that actions fail (e.g., `assertSessionHasErrors`) but don't verify the database was unchanged:
```php
// Asserts error, but doesn't verify no contact was created
```
**Fix:** Add `assertDatabaseCount` or `assertDatabaseMissing` after failure assertions.

### TQ3: No Tests for Pagination
**Files:** All index tests
**Severity:** Medium
**Issue:** After the UI overhaul changed from `->get()` to `->paginate(15)`, there are no tests verifying:
- Pagination links are present
- Correct items on page 1 vs page 2
- `?page=2` works correctly
- Pagination count is accurate
**Fix:** Add pagination tests.

---

## 🔵 LOW — Code Quality

### D18: Test Setup Duplication
**Files:** All test files
**Severity:** Low
**Issue:** Most tests create users and contacts from scratch. There's no shared test trait or helper for common setup patterns (e.g., "create two users with accepted contact and conversation").
**Fix:** Consider a `TestHelpers` trait:
```php
trait TestHelpers {
    protected function createContactPair(): array { ... }
    protected function createConversation(User $a, User $b): Conversation { ... }
}
```
`MessageTest` already has `createContactAndConversation()` — this pattern could be shared.

### D19: No Unit Tests
**Severity:** Low
**Issue:** All tests are Feature tests (HTTP requests). No unit tests for:
- Model scope methods
- Helper methods (getOtherUser, involvesUser, hasParticipant)
- Validation rule logic
**Fix:** Add unit tests for model methods.

---

## Improvement Summary

| ID | Severity | Category | Description |
|----|----------|----------|-------------|
| T1 | 🟡 Medium | Coverage | No rate limiting tests |
| T2 | 🟡 Medium | Coverage | No race condition tests |
| T3 | 🔵 Low | Coverage | Limited encryption edge cases |
| T4 | 🟡 Medium | Coverage | No scheduled command tests |
| T5 | 🟡 Medium | Coverage | No conversation exclusion tests |
| T6 | 🔵 Low | Coverage | Limited block cascade edge cases |
| T7 | 🟡 Medium | Coverage | Force-delete edge cases |
| TQ1 | 🔵 Low | Quality | No flash message assertions |
| TQ2 | 🔵 Low | Quality | No DB state check after failures |
| TQ3 | 🟡 Medium | Quality | No pagination tests |
| D18 | 🔵 Low | Code Quality | Test setup duplication |
| D19 | 🔵 Low | Code Quality | No unit tests |
