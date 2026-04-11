# Audit: Seeders & Factories

## Files Covered
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/ContactSeeder.php`
- `database/seeders/ConversationSeeder.php`
- `database/seeders/MessageSeeder.php`
- `database/seeders/BlockSeeder.php`
- `database/seeders/IgnoreSeeder.php`
- `database/seeders/TrashSeeder.php`
- `database/factories/ContactFactory.php`
- `database/factories/ConversationFactory.php`
- `database/factories/MessageFactory.php`
- `database/factories/BlockFactory.php`
- `database/factories/IgnoreFactory.php`
- `database/factories/TrashFactory.php`

---

## 🟡 MEDIUM — Bugs

### B9: DatabaseSeeder — `$users` Variable Unused
**File:** `DatabaseSeeder.php`
**Severity:** Low
**Issue:** `$users = User::factory(50)->create();` stores the result but never uses it. The seeders re-query users from the database. While this works, it's an unnecessary variable assignment.
**Fix:** Remove the variable assignment or pass it to seeders.

### B10: ContactSeeder — Assumes Sequential User IDs
**File:** `ContactSeeder.php`
**Severity:** Medium
**Issue:** The seeder documentation says "Users index 25–27 (IDs ~27–29)" but the actual IDs depend on auto-increment values and could differ if there are deleted users or if the database is not freshly migrated. The code uses `$others->slice()` correctly (by collection index, not DB ID), so the code works, but the comments are misleading.
**Fix:** Update comments to reference indices, not assumed IDs.

### B11: TrashSeeder — Direct Contact Query Without `between()` Scope
**File:** `TrashSeeder.php`
**Severity:** Low
**Issue:** The seeder manually builds the `between` query instead of using the `Contact::between()` scope:
```php
Contact::where(function ($q) use ($me, $user) {
    $q->where('user_id', $me->id)->where('contact_user_id', $user->id);
})->orWhere(function ($q) use ($me, $user) {
    $q->where('user_id', $user->id)->where('contact_user_id', $me->id);
})->firstOrFail();
```
**Fix:** Use `Contact::between($me->id, $user->id)->firstOrFail()`.

---

## 🟡 MEDIUM — Performance

### P15: MessageSeeder — Creates Messages One-by-One
**File:** `MessageSeeder.php`
**Severity:** Medium
**Issue:** Each message is created with `Message::create()` in a loop. For 44 conversations × ~20 messages each = ~880 messages, this means ~880 individual INSERT queries. With encryption (via the `encrypted` cast), each also encrypts the body.
**Fix:** Use bulk insert with manual encryption, or batch with `insert()`:
```php
Message::insert($batchedMessages); // skip encrypted cast, encrypt manually
```
Or accept the slow seeding time (it's development-only).

### P16: ConversationSeeder — `each()` Creates Conversations One-by-One
**File:** `ConversationSeeder.php`
**Severity:** Low
**Issue:** `Contact::where('status', 'accepted')->each()` loads all accepted contacts into memory and creates conversations one by one.
**Fix:** Acceptable for seeding. Could use `chunkById` for very large datasets.

---

## 🔵 LOW — Security

### S19: Seeders Create Predictable Data
**Files:** All seeders
**Severity:** Info
**Issue:** The seeders create users with `test@example.com` and predictable relationships. This is fine for development but:
- The seeder should NEVER run in production
- The `DatabaseSeeder` doesn't check the environment
**Fix:** Add environment guard:
```php
if (app()->isProduction()) {
    $this->command->error('Cannot seed in production!');
    return;
}
```

---

## 🔵 LOW — Code Quality

### D16: Factories — Missing Relationship Factories
**File:** `ConversationFactory.php`
**Severity:** Low
**Issue:** The ConversationFactory doesn't enforce canonical ordering (`user_one_id < user_two_id`). If used in tests, it could create invalid conversations.
**Fix:** Add `afterMaking` hook:
```php
public function definition(): array
{
    $users = User::factory(2)->create();
    return [
        'user_one_id' => min($users[0]->id, $users[1]->id),
        'user_two_id' => max($users[0]->id, $users[1]->id),
    ];
}
```
Or add a configure method.

### D17: No Seeder for Cross-Feature Scenarios
**Files:** All seeders
**Severity:** Low
**Issue:** The seeders create isolated scenarios but don't test cross-feature interactions well. For example:
- No user with both active ignore AND trash on the same contact
- No user with a pending request from a blocked user (edge case)
- No expired ignore records (all are active)
**Fix:** Add edge-case seed data for manual testing.

---

## Improvement Summary

| ID | Severity | Category | Description |
|----|----------|----------|-------------|
| B9 | 🔵 Low | Bug | Unused $users variable |
| B10 | 🟡 Medium | Bug | Comments assume sequential IDs |
| B11 | 🔵 Low | Bug | Manual between query instead of scope |
| P15 | 🟡 Medium | Performance | Messages created one-by-one |
| P16 | 🔵 Low | Performance | Conversations created one-by-one |
| S19 | 🔵 Info | Security | No production guard on seeders |
| D16 | 🔵 Low | Code Quality | Factory doesn't enforce canonical ordering |
| D17 | 🔵 Low | Code Quality | No cross-feature edge case seeds |
