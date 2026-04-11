# Audit: Routes, Console Commands & Scheduling

## Files Covered
- `routes/web.php`
- `routes/console.php`
- `app/Console/Commands/CleanExpiredIgnores.php`
- `app/Console/Commands/CleanExpiredTrashes.php`
- `bootstrap/app.php`

---

## 🔴 CRITICAL — Security

### S14: No Rate Limiting Middleware on Routes
**File:** `routes/web.php`
**Severity:** Critical
**Issue:** The route group only applies `auth` middleware:
```php
Route::middleware(['auth'])->group(function () { ... });
```
No `throttle` middleware. Every endpoint is vulnerable to brute-force/abuse. Critical for:
- Contact request spam (email enumeration via `exists:users,email`)
- Message flooding
- Block/unblock cycling
**Fix:** Add throttle middleware:
```php
Route::middleware(['auth', 'throttle:api'])->group(function () { ... });
```
Or define custom rate limiters in `AppServiceProvider::boot()`:
```php
RateLimiter::for('chat', fn (Request $request) => Limit::perMinute(60)->by($request->user()->id));
RateLimiter::for('write', fn (Request $request) => Limit::perMinute(10)->by($request->user()->id));
```

### S15: No CSRF Verification Exclusions Configured
**File:** `bootstrap/app.php`
**Severity:** Info
**Issue:** CSRF protection is enabled by default (Laravel's `VerifyCsrfToken` middleware). All forms include `@csrf`. This is correct — no issues found.

---

## 🟠 HIGH — Security

### S16: Contact Email Enumeration via Store Endpoint
**File:** `routes/web.php` + `StoreContactRequest.php`
**Severity:** Medium
**Issue:** The `contacts.store` endpoint validates `exists:users,email`, which reveals whether an email is registered. Combined with no rate limiting (S14), an attacker can enumerate all registered emails by submitting requests and checking validation errors.
**Fix:**
1. Add rate limiting (S14)
2. Consider returning a generic "Request sent or email not found" success message instead of a validation error
3. Or accept this as an inherent design trade-off (contacts work by email)

---

## 🟡 MEDIUM — Production

### PR5: Cleanup Commands Run Every Minute
**File:** `routes/console.php`
**Severity:** Medium
**Issue:** Both cleanup commands run `everyMinute()`:
```php
Schedule::command('app:clean-expired-ignores')->everyMinute();
Schedule::command('app:clean-expired-trashes')->everyMinute();
```
This means:
- 1440 executions per day per command (2880 total)
- Each execution queries the database even if nothing is expired
- `CleanExpiredTrashes` does cascading deletes, which could conflict with user operations
**Fix:** Change to `hourly()` or `everyFifteenMinutes()`. Expiry precision to the minute is rarely needed.

### PR6: CleanExpiredTrashes — No Transaction Wrapper
**File:** `CleanExpiredTrashes.php`
**Severity:** Medium
**Issue:** The command deletes messages, conversations, contacts, and trash records in sequence without a transaction. If the process crashes mid-deletion, data integrity is compromised.
**Fix:** Wrap the per-item operations in `DB::transaction()`:
```php
DB::transaction(function () use ($trash) {
    // all deletes here
});
```

### PR7: CleanExpiredTrashes — Race Condition with User Actions
**File:** `CleanExpiredTrashes.php`
**Severity:** Medium
**Issue:** The command loads expired trash records and processes them. Between loading and processing, a user could:
1. Restore the trash entry (delete it from trash table)
2. The command then tries to delete the contact — which is now restored
This could cause `ModelNotFoundException` or silently delete a restored contact.
**Fix:** Use pessimistic locking:
```php
Trash::expired()->lockForUpdate()->chunkById(100, function ($trashes) { ... });
```
Or re-check the trash record exists before processing.

### PR8: CleanExpiredIgnores — Could Use Bulk Delete
**File:** `CleanExpiredIgnores.php`
**Severity:** Low
**Issue:** `Ignore::expired()->delete()` is already efficient (single bulk SQL delete). Good implementation. No action needed.

---

## 🟡 MEDIUM — Code Quality

### D11: Route Organization
**File:** `routes/web.php`
**Severity:** Low
**Issue:** All routes are in a single flat group. As the app grows, consider grouping by domain:
```php
Route::prefix('contacts')->group(function () { ... });
```
Current structure is fine for the current size.

### D12: Missing `verified` Middleware
**File:** `routes/web.php`
**Severity:** Info
**Issue:** The user spec says "email verified is not necessary." This is intentional — no `verified` middleware needed.

### D13: Missing Health Check for Chat Features
**File:** `bootstrap/app.php`
**Severity:** Low
**Issue:** There's a `/up` health endpoint but it only checks if Laravel boots. No check for:
- Database connectivity
- Encryption key validity
- Scheduler running
**Fix:** Add custom health checks:
```php
->withRouting(health: '/up')
```
Consider adding `php artisan health:check` or custom endpoint.

---

## 🔵 LOW — Error Handling

### E6: Commands Don't Handle Exceptions
**Files:** Both cleanup commands
**Severity:** Low
**Issue:** Neither command has try/catch. If a database error occurs, the command crashes and the scheduler marks it as failed, but there's no alerting or retry logic.
**Fix:** Add try/catch with logging:
```php
try {
    // process
} catch (\Throwable $e) {
    Log::error('Cleanup failed', ['error' => $e->getMessage()]);
    return self::FAILURE;
}
```

---

## Improvement Summary

| ID | Severity | Category | Description |
|----|----------|----------|-------------|
| S14 | 🔴 Critical | Security | No rate limiting on routes |
| S15 | ✅ Info | Security | CSRF properly configured |
| S16 | 🟡 Medium | Security | Email enumeration via contact store |
| PR5 | 🟡 Medium | Production | Cleanup commands too frequent |
| PR6 | 🟡 Medium | Production | No transaction in trash cleanup |
| PR7 | 🟡 Medium | Production | Race condition in trash cleanup |
| PR8 | ✅ Good | Production | Ignore cleanup efficient |
| D11 | 🔵 Low | Code Quality | Route organization fine for now |
| D12 | ✅ Info | Code Quality | No verified middleware — intentional |
| D13 | 🔵 Low | Production | No feature health checks |
| E6 | 🔵 Low | Error Handling | Commands lack exception handling |
