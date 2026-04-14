# Reverb Real-Time Conversations Design

## Problem

The conversation show page uses JavaScript `setInterval` polling every 5 seconds — fetching the full page HTML, parsing it, and diffing `#messages-container` innerHTML. This is inefficient, has 5-second latency, and generates unnecessary server load. We need real-time message delivery using Laravel Reverb.

## Approach

Replace the polling mechanism with Laravel Reverb WebSocket broadcasting. Convert the conversation show page to a Livewire component that listens for broadcast events via Echo. Move message sending from a POST route into a Livewire action. Add typing indicators via client-side Echo whispers.

## Scope

- **In scope**: Conversation show page real-time messages, typing indicators, Livewire conversion, Reverb installation
- **Out of scope**: Conversation list/dashboard real-time updates, online presence indicators, read receipts

## Architecture

### Overview

The conversation show page becomes a **Livewire `ConversationShow` component** that:

- Replaces the current controller-rendered Blade view
- Manages messages state, sending, and real-time listening
- Subscribes to a **private Echo channel** `conversation.{conversationId}`
- Listens for `MessageSent` broadcast events to append new messages
- Uses Echo `whisper`/`listenForWhisper` for typing indicators (purely client-side via Alpine.js)

The `ConversationController::show()` remains as the route handler but its Blade view renders the Livewire component. The controller still loads `$conversation`, `$otherUser`, `$isIgnored`, `$isTrashed`, and `$isBlocked` — but no longer loads `$messages` (the Livewire component handles its own message loading and pagination). The `MessageController::store()` route is removed — sending moves into the Livewire component's `sendMessage()` action.

### Channel Strategy

Private channel per conversation: `conversation.{conversationId}`

- Simple authorization: user must be a participant
- Supports client events (whispers) for typing indicators
- No presence overhead

## Backend

### `MessageSent` Event

File: `app/Events/MessageSent.php`

- Implements `ShouldBroadcast`
- Broadcasts on `PrivateChannel('conversation.' . $this->message->conversation_id)`
- Constructor: accepts `Message $message` (loaded with `sender` relation)
- `broadcastWith()` returns controlled payload:
  - `id`: message ID
  - `conversation_id`: conversation ID
  - `sender_id`: sender's user ID
  - `sender_name`: sender's name
  - `body`: decrypted message body
  - `created_at`: ISO 8601 timestamp
- `broadcastAs()` returns `'MessageSent'`

### Channel Authorization

File: `routes/channels.php`

```php
Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId) {
    $conversation = Conversation::find($conversationId);
    return $conversation && $conversation->hasParticipant($user->id);
});
```

### Reverb Configuration

- Install: `composer require laravel/reverb`
- Scaffold: `php artisan install:broadcasting`
- `.env` changes:
  - `BROADCAST_CONNECTION=reverb`
  - Reverb host, port, app ID, key, secret vars
- Add Reverb server to `composer run dev` concurrently command

## Livewire Component

### `ConversationShow` Component

File: `app/Livewire/ConversationShow.php`

**Props (via mount)**:
- `Conversation $conversation` (with userOne, userTwo loaded)
- `$otherUser` (derived from conversation)
- `$isIgnored`, `$isTrashed`, `$isBlocked` (same logic as current controller)

**Message Loading**:
- Component uses Livewire's `WithPagination` trait
- `mount()` sets up conversation context; messages are queried in a computed property or render method using `$this->conversation->messages()->with('sender')->oldest()->paginate(50)`
- Auto-navigates to the last page on initial load (same as current controller logic)
- New messages received via WebSocket trigger a re-render which refreshes the paginated query, keeping the user on the last page
- Pagination links allow browsing older messages

**State**:
- `$body` — bound to message input via `wire:model`

**Actions**:
- `sendMessage()`:
  1. Validates `$body` using `MessageValidationRules` trait
  2. Authorizes via `Gate::authorize('create', [Message::class, $this->conversation])`
  3. Creates `Message` model
  4. Dispatches `MessageSent` broadcast event
  5. Clears `$body`
  6. Appends message to local `$messages` collection
  7. Dispatches browser event for scroll-to-bottom

**Echo Listeners**:
- `getListeners()` returns `['echo-private:conversation.{conversationId},MessageSent' => 'onMessageReceived']`
- `onMessageReceived($event)`: if sender is not self, appends message to `$messages`, dispatches scroll-to-bottom browser event

**Property**: `$conversationId` — computed from `$conversation->id`, used in listener channel name

### Blade View

File: `resources/views/livewire/conversation-show.blade.php`

Same visual structure as current `conversations/show.blade.php`:

- Header with back button, avatar, name
- Callouts for ignored/trashed states
- Messages container with scrolling
- Floating input area

Key changes:
- Form uses `wire:submit="sendMessage"` instead of POST
- Input uses `wire:model="body"`
- Messages rendered from component's `$messages` property
- **Polling script completely removed**
- Scroll-to-bottom via Livewire JS hooks
- Typing indicator shown via Alpine.js

### Typing Indicators (Pure Alpine/JS)

Typing is handled entirely client-side, no server roundtrip:

1. Alpine component wraps the conversation view with Echo channel subscription
2. On text input `@input` event (debounced), Alpine calls `Echo.private('conversation.{id}').whisper('typing', { userId, name })`
3. Alpine listens for whisper: `channel.listenForWhisper('typing', callback)` → sets `isTyping = true`
4. Auto-clears after 3 seconds of no typing events
5. Shows small "User is typing..." text below messages area

## Frontend

### NPM Packages

- `laravel-echo`
- `pusher-js`

### Echo Configuration

File: `resources/js/app.js`

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

## Migration Path

### Removed

- `app/Http/Controllers/MessageController.php` — sending moves to Livewire
- `app/Http/Requests/StoreMessageRequest.php` — validation logic reused in Livewire via trait
- `messages.store` route from `routes/web.php`
- Polling `<script>` block from conversation show view

### Added

- `laravel/reverb` composer package
- `laravel-echo` + `pusher-js` npm packages
- `app/Events/MessageSent.php`
- `app/Livewire/ConversationShow.php`
- `resources/views/livewire/conversation-show.blade.php`
- `routes/channels.php`
- Echo config in `resources/js/app.js`
- Reverb env vars in `.env` and `.env.example`
- Updated `composer run dev` to include Reverb server
- New/updated test files

### Preserved

- `ConversationController::show()` — loads data, renders Livewire component
- `MessagePolicy` — reused for authorization in Livewire action
- `MessageValidationRules` trait — reused in Livewire component
- All existing models, migrations, factories
- `ConversationController::index()` — unchanged

### View Change

The `conversations/show.blade.php` view changes from rendering messages directly to rendering the Livewire component:

```blade
<x-layouts::app :title="$otherUser->name">
    <livewire:conversation-show
        :conversation="$conversation"
        :other-user="$otherUser"
        :is-ignored="$isIgnored"
        :is-trashed="$isTrashed"
        :is-blocked="$isBlocked"
    />
</x-layouts::app>
```

## Testing

### New Tests

- **`ConversationShowLivewireTest.php`** — Livewire component tests:
  - Component renders with messages
  - User can send a message (appears in DB and component state)
  - Validation: empty body, too-long body
  - Authorization: blocked user can't send, ignored user can't send, trashed contact can't send, non-participant can't send
  - `MessageSent` event is dispatched on send
  - Component renders typing indicator area

- **`MessageSentEventTest.php`** — Event/broadcast tests:
  - Event broadcasts on correct private channel
  - Event payload shape is correct
  - Event implements ShouldBroadcast

- **`BroadcastChannelAuthTest.php`** — Channel auth tests:
  - Participant can authorize on conversation channel
  - Non-participant cannot authorize
  - Guest cannot authorize

### Updated Tests

- **`MessageTest.php`** — Remove tests for the deleted `messages.store` route. Core message logic (validation, authorization, encryption) is now tested via `ConversationShowLivewireTest`
- **`ConversationTest.php`** — Show page tests may need minor updates since the view now renders a Livewire component (assertSee should still work)

## Dev Workflow

Updated `composer run dev` adds Reverb server to concurrently:

```
npx concurrently ... "php artisan reverb:start" ... --names=server,queue,logs,vite,reverb
```
