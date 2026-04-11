# Audit: Views & Templates

## Files Covered
- `resources/views/dashboard.blade.php`
- `resources/views/contacts/index.blade.php`
- `resources/views/contacts/show.blade.php`
- `resources/views/contacts/create.blade.php`
- `resources/views/conversations/index.blade.php`
- `resources/views/conversations/show.blade.php`
- `resources/views/blocks/index.blade.php`
- `resources/views/ignores/index.blade.php`
- `resources/views/trashes/index.blade.php`
- `resources/views/vendor/pagination/tailwind.blade.php`

---

## 🔴 CRITICAL — Security

### S17: XSS in JavaScript confirm() Dialogs
**Files:** `contacts/index.blade.php`, `contacts/show.blade.php`, `trashes/index.blade.php`, `blocks/index.blade.php`, `ignores/index.blade.php`
**Severity:** High
**Issue:** Multiple confirm dialogs use `__()` translations inside JavaScript:
```html
onsubmit="return confirm('{{ __('Decline this request?') }}')"
```
While the `__()` function returns translated strings and `{{ }}` escapes HTML entities, it does NOT escape single quotes for JavaScript context. If a translation string contains a single quote (e.g., in French: "Refuser cette demande d'ami?"), the JavaScript would break or be vulnerable to injection.
**Fix:** Use `Js::from()` or escape for JavaScript:
```html
onsubmit="return confirm({{ Js::from(__('Decline this request?')) }})"
```

### S18: contacts/show.blade.php — User Name in Confirm Dialogs
**File:** `contacts/show.blade.php`
**Severity:** High
**Issue:** The block modal heading uses:
```blade
{{ __('Block :name?', ['name' => $otherUser->name]) }}
```
This is safe in Blade (`{{ }}` escapes HTML). However, in the delete confirm:
```html
onsubmit="return confirm('{{ __('Delete this contact?...') }}')"
```
If the translated text included the user's name AND the name contained a single quote (e.g., "O'Brien"), it would break the JavaScript.
The current delete confirm doesn't include the name, so it's safe. But be cautious if this pattern is extended.
**Fix:** Use `Js::from()` for all confirm dialogs as a preventive measure.

---

## 🟡 MEDIUM — Performance

### P12: conversations/show.blade.php — No Message Pagination
**File:** `conversations/show.blade.php`
**Severity:** Medium
**Issue:** All messages are rendered in a single `@forelse` loop. For conversations with hundreds of messages, the page will:
- Render all messages as HTML (large DOM)
- Decrypt all message bodies (CPU-intensive due to encrypted cast)
- Load everything into memory
**Fix:** Add message pagination, cursor pagination, or "load more" functionality.

### P13: Dashboard — Multiple Queries in View (N+1)
**File:** `dashboard.blade.php`
**Severity:** Low
**Issue:** The view calls `$conversation->getOtherUser(auth()->id())` which accesses eager-loaded relationships. This is fine — the controller already eager-loads `userOne` and `userTwo`. No N+1 here.

### P14: Contacts Index — Calls getOtherUser in Loop
**File:** `contacts/index.blade.php`
**Severity:** Low
**Issue:** `$contact->getOtherUser(auth()->id())` is called per-row. Since `user` and `contactUser` are eager-loaded, this is fine.

---

## 🟡 MEDIUM — UX / Accessibility

### UX1: conversations/show.blade.php — No Auto-Scroll to Bottom
**File:** `conversations/show.blade.php`
**Severity:** Medium
**Issue:** When the page loads, the message list doesn't scroll to the bottom. Users see the oldest messages first and must scroll down to see recent ones.
**Fix:** Add JavaScript to scroll to the bottom on page load:
```html
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.querySelector('[data-messages]');
        if (container) container.scrollTop = container.scrollHeight;
    });
</script>
```

### UX2: conversations/show.blade.php — No Real-Time Updates
**File:** `conversations/show.blade.php`
**Severity:** Medium
**Issue:** The original spec calls for "simple polling every 5 seconds." The view previously had `wire:poll.5s` but it was removed because it's a controller-rendered view (not a Livewire component). There is currently NO auto-refresh mechanism. Users must manually refresh to see new messages.
**Fix:** Options:
1. Convert to a Livewire component with `wire:poll.5s`
2. Add meta refresh: `<meta http-equiv="refresh" content="5">`
3. Add JavaScript polling with `fetch()` and DOM updates
4. Use Laravel Echo + WebSockets

### UX3: No Empty State Illustrations
**Files:** All index views
**Severity:** Low
**Issue:** Empty states use plain `<flux:text>` with simple messages. A more polished UX would use illustrations or icons.
**Fix:** Low priority — cosmetic improvement.

### UX4: contacts/show.blade.php — "Message" Button Goes to Conversations Index
**File:** `contacts/show.blade.php`
**Severity:** Low
**Issue:** The "Message" button links to `conversations.index` (the list), not directly to the specific conversation. Users must then find the conversation in the list.
**Fix:** Link directly to the conversation:
```php
$conversation = Conversation::betweenUsers(auth()->id(), $otherUser->id)->first();
// Pass to view, then link to conversations.show
```

---

## 🟡 MEDIUM — Error Handling

### E7: contacts/show.blade.php — Modals Show Even After Validation Errors
**File:** `contacts/show.blade.php`
**Severity:** Low
**Issue:** If a modal form (block/ignore/trash) has validation errors, the page redirects back to the show page. The modal is closed, and the user doesn't see what went wrong. Validation errors from block/ignore/trash forms are not displayed.
**Fix:** Add `@error` directives near the relevant buttons, or use session flash messages from the controllers.

### E8: conversations/show.blade.php — Error Variable Collision
**File:** `conversations/show.blade.php`
**Severity:** Medium
**Issue:** The error directive uses `$message`:
```blade
@error('body')
    <p class="mt-1 text-xs text-zinc-500">{{ $message }}</p>
@enderror
```
Laravel's `@error` directive provides `$message` as the error text. However, `$message` is also a variable in scope (it's the loop variable from `@forelse ($messages as $message)`). Inside `@error`, `$message` refers to the error message (Blade's scoping), but this naming collision is confusing.
**Fix:** Rename the loop variable:
```blade
@forelse ($messages as $msg)
```

---

## 🔵 LOW — Code Quality

### D14: Dashboard View — Hardcoded "5" Threshold
**File:** `dashboard.blade.php`
**Severity:** Low
**Issue:** The "View all" button shows when `$incomingTotal > 5`. The `5` is hardcoded and matches the controller's `limit(5)`, but they're not linked. If one changes, the other must too.
**Fix:** Pass a constant or compute in the controller.

### D15: Inconsistent Date Formatting
**Files:** Various views
**Severity:** Low
**Issue:**
- `conversations/show.blade.php`: `$message->created_at->format('H:i')` — time only
- `ignores/index.blade.php`: `$ignore->expires_at->format('M d, Y H:i')` — full datetime
- `blocks/index.blade.php`: `$block->created_at->diffForHumans()` — relative
- `trashes/index.blade.php`: `$trash->expires_at->format('M d, Y')` — date only
- `dashboard.blade.php`: `$lastMessage->created_at->diffForHumans()` — relative
**Fix:** Standardize date formatting across views, or intentionally document the different formats per context.

---

## Improvement Summary

| ID | Severity | Category | Description |
|----|----------|----------|-------------|
| S17 | 🟠 High | Security | XSS in JS confirm() via translations |
| S18 | 🟠 High | Security | User name could break JS confirm |
| P12 | 🟡 Medium | Performance | No message pagination in chat |
| P13 | ✅ Good | Performance | Dashboard properly eager-loads |
| P14 | ✅ Good | Performance | Contacts index properly eager-loads |
| UX1 | 🟡 Medium | UX | No auto-scroll to latest messages |
| UX2 | 🟡 Medium | UX | No polling/auto-refresh for messages |
| UX3 | 🔵 Low | UX | No empty state illustrations |
| UX4 | 🔵 Low | UX | Message button goes to index, not chat |
| E7 | 🔵 Low | Error Handling | Modal validation errors not visible |
| E8 | 🟡 Medium | Error Handling | $message variable collision in @error |
| D14 | 🔵 Low | Code Quality | Hardcoded threshold "5" |
| D15 | 🔵 Low | Code Quality | Inconsistent date formatting |
