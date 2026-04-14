# Reverb Real-Time Conversations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace JavaScript polling with Laravel Reverb WebSocket broadcasting for real-time message delivery and typing indicators on the conversation show page.

**Architecture:** Install Laravel Reverb and Laravel Echo. Create a `MessageSent` broadcast event dispatched when messages are created. Convert the conversation show page from a controller-rendered Blade view to a Livewire `ConversationShow` component that listens for broadcast events on a private channel. Typing indicators use client-side Echo whispers via Alpine.js. Remove the old `MessageController`, `StoreMessageRequest`, polling script, and `messages.store` route.

**Tech Stack:** Laravel Reverb, Laravel Echo, Pusher JS, Livewire 4, Alpine.js, PHPUnit

**Spec:** `docs/superpowers/specs/2026-04-14-reverb-realtime-conversations-design.md`

---

## File Map

### New Files

| File | Responsibility |
|------|---------------|
| `app/Events/MessageSent.php` | Broadcast event dispatched when a message is created |
| `app/Livewire/ConversationShow.php` | Livewire component: message display, sending, real-time listening |
| `resources/views/livewire/conversation-show.blade.php` | Livewire Blade view for conversation with typing indicator |
| `routes/channels.php` | Broadcast channel authorization |
| `tests/Feature/MessageSentEventTest.php` | Tests for the MessageSent broadcast event |
| `tests/Feature/BroadcastChannelAuthTest.php` | Tests for channel authorization |
| `tests/Feature/ConversationShowLivewireTest.php` | Tests for the Livewire component |

### Modified Files

| File | Change |
|------|--------|
| `resources/js/app.js` | Add Echo + Pusher configuration |
| `resources/views/conversations/show.blade.php` | Replace full view with Livewire component embed |
| `app/Http/Controllers/ConversationController.php` | Remove `$messages` loading from `show()` method |
| `routes/web.php` | Remove `messages.store` route |
| `.env` | Update `BROADCAST_CONNECTION`, add Reverb vars |
| `.env.example` | Add Reverb env var placeholders |
| `composer.json` | Add Reverb to dev script |
| `package.json` | Add `laravel-echo` and `pusher-js` dependencies |
| `tests/Feature/MessageTest.php` | Remove (replaced by ConversationShowLivewireTest) |
| `tests/Feature/ConversationTest.php` | Minor adjustments for Livewire-rendered show page |
| `bootstrap/app.php` | Add broadcasting route registration |

### Deleted Files

| File | Reason |
|------|--------|
| `app/Http/Controllers/MessageController.php` | Message sending moves to Livewire component |
| `app/Http/Requests/StoreMessageRequest.php` | Validation reused via `MessageValidationRules` trait directly in Livewire |

---

## Task 1: Install Reverb & Broadcasting Infrastructure

**Files:**
- Modify: `composer.json` (via composer)
- Modify: `package.json` (via npm)
- Modify: `.env`
- Modify: `.env.example`
- Create: `config/broadcasting.php` (via artisan)
- Create: `routes/channels.php` (via artisan, will be populated in Task 4)
- Modify: `resources/js/app.js`

- [ ] **Step 1: Install Laravel Reverb**

Run:
```bash
composer require laravel/reverb --no-interaction
```

- [ ] **Step 2: Install broadcasting scaffolding**

Run:
```bash
php artisan install:broadcasting --no-interaction
```

This creates `config/broadcasting.php`, `routes/channels.php`, updates `resources/js/app.js` with Echo setup, and installs `laravel-echo` + `pusher-js` npm packages.

- [ ] **Step 3: Verify the scaffolded Echo config in `resources/js/app.js`**

The file should now contain Echo configuration. Verify it looks like:

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

If it doesn't match this structure, update it to match. The key details: broadcaster is `'reverb'`, using `import.meta.env.VITE_REVERB_*` variables.

- [ ] **Step 4: Update `.env` with Reverb configuration**

Change `BROADCAST_CONNECTION=log` to `BROADCAST_CONNECTION=reverb` and ensure Reverb vars are present. The `php artisan install:broadcasting` command should have added Reverb env vars. Verify these exist in `.env`:

```
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=my-app-id
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-app-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

If any are missing, add them.

- [ ] **Step 5: Update `.env.example` with Reverb placeholders**

Add the same Reverb env vars to `.env.example` (with empty/default values):

```
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=my-app-id
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-app-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

- [ ] **Step 6: Verify broadcasting route is registered in `bootstrap/app.php`**

Check `bootstrap/app.php`. The `install:broadcasting` command should have updated `withRouting()` to include `channels: __DIR__.'/../routes/channels.php'`. If not, add it:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    channels: __DIR__.'/../routes/channels.php',
    health: '/up',
)
```

- [ ] **Step 7: Build frontend assets**

Run:
```bash
npm run build
```

Expected: Build succeeds without errors.

- [ ] **Step 8: Commit**

```
feat: install Laravel Reverb and broadcasting infrastructure
```

---

## Task 2: Create MessageSent Broadcast Event (TDD)

**Files:**
- Create: `tests/Feature/MessageSentEventTest.php`
- Create: `app/Events/MessageSent.php`

- [ ] **Step 1: Create the test file**

Run:
```bash
php artisan make:test MessageSentEventTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing tests**

Replace the contents of `tests/Feature/MessageSentEventTest.php` with:

```php
<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageSentEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_implements_should_broadcast(): void
    {
        $message = $this->createMessage();

        $event = new MessageSent($message);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_event_broadcasts_on_correct_private_channel(): void
    {
        $message = $this->createMessage();

        $event = new MessageSent($message);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('conversation.' . $message->conversation_id, $channels[0]->name);
    }

    public function test_event_has_correct_broadcast_name(): void
    {
        $message = $this->createMessage();

        $event = new MessageSent($message);

        $this->assertEquals('MessageSent', $event->broadcastAs());
    }

    public function test_event_payload_contains_required_fields(): void
    {
        $message = $this->createMessage();
        $message->load('sender');

        $event = new MessageSent($message);
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('conversation_id', $payload);
        $this->assertArrayHasKey('sender_id', $payload);
        $this->assertArrayHasKey('sender_name', $payload);
        $this->assertArrayHasKey('body', $payload);
        $this->assertArrayHasKey('created_at', $payload);

        $this->assertEquals($message->id, $payload['id']);
        $this->assertEquals($message->conversation_id, $payload['conversation_id']);
        $this->assertEquals($message->sender_id, $payload['sender_id']);
        $this->assertEquals($message->sender->name, $payload['sender_name']);
        $this->assertEquals($message->body, $payload['body']);
    }

    public function test_event_payload_contains_decrypted_body(): void
    {
        $message = $this->createMessage();
        $message->load('sender');

        $event = new MessageSent($message);
        $payload = $event->broadcastWith();

        $this->assertEquals('Hello, world!', $payload['body']);
    }

    private function createMessage(): Message
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        return Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userA->id,
            'body' => 'Hello, world!',
        ]);
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run:
```bash
php artisan test --compact tests/Feature/MessageSentEventTest.php
```

Expected: FAIL — class `App\Events\MessageSent` not found.

- [ ] **Step 4: Create the MessageSent event**

Create `app/Events/MessageSent.php`:

```php
<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
        $this->message->loadMissing('sender');
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /**
     * @return array{id: int, conversation_id: int, sender_id: int, sender_name: string, body: string, created_at: string}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender->name,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run:
```bash
php artisan test --compact tests/Feature/MessageSentEventTest.php
```

Expected: All 5 tests PASS.

- [ ] **Step 6: Run Pint**

Run:
```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```
feat: add MessageSent broadcast event
```

---

## Task 3: Create Broadcast Channel Authorization (TDD)

**Files:**
- Create: `tests/Feature/BroadcastChannelAuthTest.php`
- Modify: `routes/channels.php`

- [ ] **Step 1: Create the test file**

Run:
```bash
php artisan make:test BroadcastChannelAuthTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing tests**

Replace the contents of `tests/Feature/BroadcastChannelAuthTest.php` with:

```php
<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_authorize_on_conversation_channel(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        $this->actingAs($userA)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-conversation.' . $conversation->id,
            ])
            ->assertOk();
    }

    public function test_other_participant_can_authorize_on_conversation_channel(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        $this->actingAs($userB)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-conversation.' . $conversation->id,
            ])
            ->assertOk();
    }

    public function test_non_participant_cannot_authorize_on_conversation_channel(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $outsider = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        $this->actingAs($outsider)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-conversation.' . $conversation->id,
            ])
            ->assertForbidden();
    }

    public function test_guest_cannot_authorize_on_conversation_channel(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        $this->post('/broadcasting/auth', [
            'channel_name' => 'private-conversation.' . $conversation->id,
        ])->assertUnauthorized();
    }

    public function test_nonexistent_conversation_denies_authorization(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-conversation.999999',
            ])
            ->assertForbidden();
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run:
```bash
php artisan test --compact tests/Feature/BroadcastChannelAuthTest.php
```

Expected: FAIL — channel authorization not configured.

- [ ] **Step 4: Add channel authorization to `routes/channels.php`**

Replace the contents of `routes/channels.php` with:

```php
<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId): bool {
    $conversation = Conversation::find($conversationId);

    return $conversation !== null && $conversation->hasParticipant($user->id);
});
```

- [ ] **Step 5: Run tests to verify they pass**

Run:
```bash
php artisan test --compact tests/Feature/BroadcastChannelAuthTest.php
```

Expected: All 5 tests PASS.

- [ ] **Step 6: Run Pint**

Run:
```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```
feat: add broadcast channel authorization for conversations
```

---

## Task 4: Create ConversationShow Livewire Component — Rendering (TDD)

**Files:**
- Create: `tests/Feature/ConversationShowLivewireTest.php`
- Create: `app/Livewire/ConversationShow.php`
- Create: `resources/views/livewire/conversation-show.blade.php`

- [ ] **Step 1: Create the test file**

Run:
```bash
php artisan make:test ConversationShowLivewireTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing rendering tests**

Replace the contents of `tests/Feature/ConversationShowLivewireTest.php` with:

```php
<?php

namespace Tests\Feature;

use App\Livewire\ConversationShow;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConversationShowLivewireTest extends TestCase
{
    use RefreshDatabase;

    private function createContactAndConversation(): array
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $contact = Contact::factory()->accepted()->create([
            'user_id' => $userA->id,
            'contact_user_id' => $userB->id,
        ]);

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        return [$userA, $userB, $contact, $conversation];
    }

    public function test_component_renders_with_messages(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userA->id,
            'body' => 'Hello from A',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userB->id,
            'body' => 'Hello from B',
        ]);

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->assertSee('Hello from A')
            ->assertSee('Hello from B')
            ->assertSee($userB->name);
    }

    public function test_component_renders_empty_state(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->assertSee('No messages yet');
    }

    public function test_component_shows_ignored_callout(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $ignore = \App\Models\Ignore::create([
            'ignorer_id' => $userB->id,
            'ignored_id' => $userA->id,
            'expires_at' => now()->addDay(),
        ]);

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => $ignore,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->assertSee('unavailable until');
    }

    public function test_component_shows_trashed_callout(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => true,
                'isBlocked' => false,
            ])
            ->assertSee('Restore to see new messages');
    }

    public function test_component_hides_input_when_trashed(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => true,
                'isBlocked' => false,
            ])
            ->assertDontSee('Type a message...');
    }

    public function test_component_hides_input_when_ignored(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $ignore = \App\Models\Ignore::create([
            'ignorer_id' => $userB->id,
            'ignored_id' => $userA->id,
            'expires_at' => now()->addDay(),
        ]);

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => $ignore,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->assertDontSee('Type a message...');
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run:
```bash
php artisan test --compact tests/Feature/ConversationShowLivewireTest.php
```

Expected: FAIL — class `App\Livewire\ConversationShow` not found.

- [ ] **Step 4: Create the Livewire component class**

Create `app/Livewire/ConversationShow.php`:

```php
<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\Ignore;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class ConversationShow extends Component
{
    use WithPagination;

    #[Locked]
    public Conversation $conversation;

    #[Locked]
    public User $otherUser;

    #[Locked]
    public ?Ignore $isIgnored = null;

    #[Locked]
    public bool $isTrashed = false;

    #[Locked]
    public bool $isBlocked = false;

    public string $body = '';

    public function mount(
        Conversation $conversation,
        User $otherUser,
        ?Ignore $isIgnored,
        bool $isTrashed,
        bool $isBlocked,
    ): void {
        $this->conversation = $conversation;
        $this->otherUser = $otherUser;
        $this->isIgnored = $isIgnored;
        $this->isTrashed = $isTrashed;
        $this->isBlocked = $isBlocked;
    }

    public function render(): View
    {
        $messagesQuery = $this->conversation->messages()->with('sender')->oldest();

        $total = $messagesQuery->count();
        $lastPage = max(1, (int) ceil($total / 50));

        $currentPage = $this->getPage();
        if ($currentPage === 1 && $lastPage > 1 && ! request()->has('page')) {
            $this->setPage($lastPage);
        }

        $messages = $messagesQuery->paginate(50);

        return view('livewire.conversation-show', [
            'messages' => $messages,
        ]);
    }
}
```

- [ ] **Step 5: Create the Livewire Blade view**

Create `resources/views/livewire/conversation-show.blade.php`:

```blade
<div class="relative flex h-full w-full flex-1 flex-col rounded-xl">
    {{-- Header --}}
    <header class="flex items-center gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
        <flux:button icon="arrow-left" :href="route('conversations.index')" wire:navigate variant="subtle" size="sm" aria-label="{{ __('Back to conversations') }}" />
        <flux:avatar size="xs" :name="$otherUser->name" />
        <flux:heading>{{ $otherUser->name }}</flux:heading>
    </header>

    @if ($isIgnored)
        <div class="px-4 pt-3" role="alert">
            <flux:callout>
                {{ __('This user is unavailable until :date.', ['date' => $isIgnored->expires_at->format('M d, Y H:i')]) }}
            </flux:callout>
        </div>
    @endif

    @if ($isTrashed)
        <div class="px-4 pt-3" role="alert">
            <flux:callout>
                {{ __('This contact is in your trash. Restore to see new messages and send messages.') }}
            </flux:callout>
        </div>
    @endif

    {{-- Messages --}}
    <div
        id="messages-container"
        class="flex-1 space-y-3 overflow-y-auto p-4 {{ (! $isTrashed && ! $isIgnored) ? 'pb-20' : '' }}"
        role="log"
        aria-label="{{ __('Messages with :name', ['name' => $otherUser->name]) }}"
        aria-live="polite"
    >
        @if ($messages->hasPages())
            <div class="mb-4">
                {{ $messages->links() }}
            </div>
        @endif

        @forelse ($messages as $msg)
            @php $isMine = $msg->sender_id === auth()->id(); @endphp
            <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-xs rounded-lg px-4 py-2 {{ $isMine ? 'bg-zinc-800 text-white dark:bg-zinc-200 dark:text-zinc-900' : 'bg-zinc-100 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100' }}">
                    <p class="text-sm">{{ $msg->body }}</p>
                    <p class="mt-1 text-xs opacity-50"><time datetime="{{ $msg->created_at->toIso8601String() }}">{{ $msg->created_at->format('H:i') }}</time></p>
                </div>
            </div>
        @empty
            <div class="flex h-full items-center justify-center">
                <flux:text class="text-zinc-400">{{ __('No messages yet. Send the first one!') }}</flux:text>
            </div>
        @endforelse
    </div>

    {{-- Floating Input --}}
    @if (! $isTrashed && ! $isIgnored)
        <div class="sticky bottom-0 z-10 border-t border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <form wire:submit="sendMessage" class="flex items-center gap-2">
                <div class="flex-1">
                    <flux:input wire:model="body" placeholder="{{ __('Type a message...') }}" required autocomplete="off" aria-label="{{ __('Message body') }}" />
                </div>
                <flux:button type="submit" variant="filled" icon="arrow-right" aria-label="{{ __('Send message') }}" />
            </form>
            @error('body')
                <p class="mt-1 text-xs text-zinc-500" role="alert">{{ $message }}</p>
            @enderror
        </div>
    @endif
</div>
```

- [ ] **Step 6: Run tests to verify they pass**

Run:
```bash
php artisan test --compact tests/Feature/ConversationShowLivewireTest.php
```

Expected: All 6 tests PASS.

- [ ] **Step 7: Run Pint**

Run:
```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 8: Commit**

```
feat: add ConversationShow Livewire component with rendering
```

---

## Task 5: Add Message Sending to Livewire Component (TDD)

**Files:**
- Modify: `tests/Feature/ConversationShowLivewireTest.php`
- Modify: `app/Livewire/ConversationShow.php`

- [ ] **Step 1: Add sending tests to `ConversationShowLivewireTest.php`**

Append these test methods to `tests/Feature/ConversationShowLivewireTest.php`:

```php
    public function test_user_can_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $userA->id,
        ]);

        $message = Message::where('conversation_id', $conversation->id)->first();
        $this->assertEquals('Hello!', $message->body);
    }

    public function test_send_message_clears_body(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage')
            ->assertSet('body', '');
    }

    public function test_send_message_dispatches_broadcast_event(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\MessageSent::class]);

        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage');

        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\MessageSent::class, function ($event) use ($conversation) {
            return $event->message->conversation_id === $conversation->id;
        });
    }

    public function test_send_message_requires_body(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', '')
            ->call('sendMessage')
            ->assertHasErrors(['body' => 'required']);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_send_message_body_max_length(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', str_repeat('a', 5001))
            ->call('sendMessage')
            ->assertHasErrors(['body' => 'max']);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_send_message_at_max_length_succeeds(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', str_repeat('a', 5000))
            ->call('sendMessage')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('messages', 1);
    }
```

- [ ] **Step 2: Run tests to verify the new tests fail**

Run:
```bash
php artisan test --compact tests/Feature/ConversationShowLivewireTest.php --filter=test_user_can_send_message
```

Expected: FAIL — method `sendMessage` does not exist.

- [ ] **Step 3: Implement `sendMessage()` in the Livewire component**

Add the following imports to `app/Livewire/ConversationShow.php`:

```php
use App\Concerns\MessageValidationRules;
use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
```

Add the trait usage inside the class:

```php
use MessageValidationRules, WithPagination;
```

Add the `sendMessage()` method to the class:

```php
    public function sendMessage(): void
    {
        $this->validate($this->messageRules());

        Gate::authorize('create', [Message::class, $this->conversation]);

        $message = Message::create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => Auth::id(),
            'body' => $this->body,
        ]);

        MessageSent::dispatch($message);

        $this->reset('body');

        $this->dispatch('scroll-to-bottom');
    }
```

- [ ] **Step 4: Run all component tests**

Run:
```bash
php artisan test --compact tests/Feature/ConversationShowLivewireTest.php
```

Expected: All 12 tests PASS.

- [ ] **Step 5: Run Pint**

Run:
```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```
feat: add message sending action to ConversationShow component
```

---

## Task 6: Add Authorization Edge Case Tests (TDD)

**Files:**
- Modify: `tests/Feature/ConversationShowLivewireTest.php`

- [ ] **Step 1: Add authorization edge case tests**

Append these test methods to `tests/Feature/ConversationShowLivewireTest.php`:

```php
    public function test_blocked_user_cannot_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        \App\Models\Block::create([
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);

        Livewire::actingAs($userB)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userA,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => true,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage')
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_blocker_cannot_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        \App\Models\Block::create([
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => true,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage')
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_ignored_user_cannot_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $ignore = \App\Models\Ignore::create([
            'ignorer_id' => $userA->id,
            'ignored_id' => $userB->id,
            'expires_at' => now()->addDay(),
        ]);

        Livewire::actingAs($userB)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userA,
                'isIgnored' => $ignore,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage')
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_trashed_contact_cannot_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        \App\Models\Trash::create([
            'user_id' => $userA->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->addDays(7),
        ]);

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => true,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage')
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_both_participants_can_send_messages(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello from A!')
            ->call('sendMessage')
            ->assertHasNoErrors();

        Livewire::actingAs($userB)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userA,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello from B!')
            ->call('sendMessage')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('messages', 2);
    }

    public function test_message_is_encrypted_in_database(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Secret message')
            ->call('sendMessage');

        $rawBody = \DB::table('messages')->first()->body;
        $this->assertNotEquals('Secret message', $rawBody);

        $message = Message::first();
        $this->assertEquals('Secret message', $message->body);
    }
```

- [ ] **Step 2: Run all component tests**

Run:
```bash
php artisan test --compact tests/Feature/ConversationShowLivewireTest.php
```

Expected: All 18 tests PASS. The authorization tests pass because the `MessagePolicy::create()` method already checks for blocks, ignores, and trashes via `Gate::authorize()`.

- [ ] **Step 3: Run Pint**

Run:
```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Commit**

```
test: add authorization edge case tests for ConversationShow
```

---

## Task 7: Add Real-Time Echo Listener to Livewire Component (TDD)

**Files:**
- Modify: `tests/Feature/ConversationShowLivewireTest.php`
- Modify: `app/Livewire/ConversationShow.php`

- [ ] **Step 1: Add Echo listener test**

Append this test method to `tests/Feature/ConversationShowLivewireTest.php`:

```php
    public function test_on_message_received_adds_message_to_view(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userB->id,
            'body' => 'Real-time message',
        ]);

        Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->call('onMessageReceived', [
                'id' => $message->id,
                'conversation_id' => $conversation->id,
                'sender_id' => $userB->id,
                'sender_name' => $userB->name,
                'body' => 'Real-time message',
                'created_at' => $message->created_at->toIso8601String(),
            ])
            ->assertSee('Real-time message');
    }

    public function test_echo_listener_is_registered_for_conversation_channel(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();

        $component = Livewire::actingAs($userA)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userB,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ]);

        $listeners = $component->instance()->getListeners();

        $expectedKey = "echo-private:conversation.{$conversation->id},MessageSent";
        $this->assertArrayHasKey($expectedKey, $listeners);
        $this->assertEquals('onMessageReceived', $listeners[$expectedKey]);
    }
```

- [ ] **Step 2: Run the new tests to verify they fail**

Run:
```bash
php artisan test --compact tests/Feature/ConversationShowLivewireTest.php --filter=test_on_message_received
```

Expected: FAIL — method `onMessageReceived` does not exist.

- [ ] **Step 3: Add Echo listener and `onMessageReceived` to the component**

Add the `getListeners()` and `onMessageReceived()` methods to `app/Livewire/ConversationShow.php`:

```php
    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            "echo-private:conversation.{$this->conversation->id},MessageSent" => 'onMessageReceived',
        ];
    }

    /**
     * @param  array{id: int, conversation_id: int, sender_id: int, sender_name: string, body: string, created_at: string}  $event
     */
    public function onMessageReceived(array $event): void
    {
        if ($event['sender_id'] === Auth::id()) {
            return;
        }

        $this->dispatch('scroll-to-bottom');
    }
```

Note: The `onMessageReceived` method doesn't need to manually append messages to a collection because the component uses `WithPagination` — it re-queries messages from the database on each render. Calling `$this->dispatch('scroll-to-bottom')` triggers a re-render which picks up the new message from the DB. The method already returns early if the sender is the current user (to avoid duplicate processing).

- [ ] **Step 4: Run all component tests**

Run:
```bash
php artisan test --compact tests/Feature/ConversationShowLivewireTest.php
```

Expected: All 20 tests PASS.

- [ ] **Step 5: Run Pint**

Run:
```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```
feat: add real-time Echo listener to ConversationShow component
```

---

## Task 8: Wire Up Controller & View, Remove Old Code

**Files:**
- Modify: `resources/views/conversations/show.blade.php`
- Modify: `app/Http/Controllers/ConversationController.php`
- Modify: `routes/web.php`
- Delete: `app/Http/Controllers/MessageController.php`
- Delete: `app/Http/Requests/StoreMessageRequest.php`

- [ ] **Step 1: Update `conversations/show.blade.php` to render the Livewire component**

Replace the entire contents of `resources/views/conversations/show.blade.php` with:

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

- [ ] **Step 2: Simplify `ConversationController::show()`**

Remove message loading from `ConversationController::show()`. Update the method to:

```php
    public function show(Request $request, Conversation $conversation): View
    {
        Gate::authorize('view', $conversation);

        $conversation->load(['userOne', 'userTwo']);

        $otherUser = $conversation->getOtherUser(Auth::id());

        $userId = Auth::id();
        $otherUserId = $otherUser->id;

        $isIgnored = Ignore::where('ignorer_id', $otherUserId)
            ->where('ignored_id', $userId)
            ->active()
            ->first();

        $isBlocked = Block::where(function ($q) use ($userId, $otherUserId) {
            $q->where('blocker_id', $userId)->where('blocked_id', $otherUserId);
        })->orWhere(function ($q) use ($userId, $otherUserId) {
            $q->where('blocker_id', $otherUserId)->where('blocked_id', $userId);
        })->exists();

        $contact = Contact::between($userId, $otherUserId)->accepted()->first();
        $isTrashed = $contact && Trash::where('user_id', $userId)->where('contact_id', $contact->id)->exists();

        return view('conversations.show', compact('conversation', 'otherUser', 'isIgnored', 'isBlocked', 'isTrashed'));
    }
```

The key change: removed `$messagesQuery`, `$total`, `$lastPage`, `$messages`, and `$messages` from the `compact()` call. The Livewire component handles its own message loading.

Also remove the `use App\Models\Message;` import from the top of the controller since it's no longer used there (check if `index()` uses it — yes it does for the `orderByDesc` subquery, so keep it).

- [ ] **Step 3: Remove the `messages.store` route from `routes/web.php`**

Remove this line from `routes/web.php`:

```php
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:chat-message')
        ->name('messages.store');
```

Also remove the `use App\Http\Controllers\MessageController;` import at the top of the file.

- [ ] **Step 4: Delete `MessageController.php`**

Delete the file `app/Http/Controllers/MessageController.php`.

- [ ] **Step 5: Delete `StoreMessageRequest.php`**

Delete the file `app/Http/Requests/StoreMessageRequest.php`.

- [ ] **Step 6: Run the existing conversation tests to verify they still pass**

Run:
```bash
php artisan test --compact tests/Feature/ConversationTest.php
```

Expected: All tests PASS. The show page tests use `assertSee` which should still work with the Livewire component rendering the same content.

- [ ] **Step 7: Run all Livewire component tests**

Run:
```bash
php artisan test --compact tests/Feature/ConversationShowLivewireTest.php
```

Expected: All 20 tests PASS.

- [ ] **Step 8: Run Pint**

Run:
```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 9: Commit**

```
refactor: wire up Livewire component, remove MessageController and polling
```

---

## Task 9: Migrate MessageTest to Livewire Tests

**Files:**
- Delete: `tests/Feature/MessageTest.php`
- Verify: `tests/Feature/ConversationShowLivewireTest.php` covers all cases

- [ ] **Step 1: Verify coverage mapping**

The old `MessageTest.php` has these tests that need coverage in the new Livewire test:

| Old MessageTest | New ConversationShowLivewireTest |
|---|---|
| `test_user_can_send_message` | `test_user_can_send_message` |
| `test_message_body_is_encrypted_in_database` | `test_message_is_encrypted_in_database` |
| `test_non_participant_cannot_send_message` | Already covered by `MessagePolicy` + `Gate::authorize` (add explicit test below) |
| `test_message_requires_body` | `test_send_message_requires_body` |
| `test_message_body_max_length` | `test_send_message_body_max_length` |
| `test_guests_cannot_send_messages` | Not applicable — Livewire component requires auth at the route level |
| `test_blocked_user_cannot_send_message` | `test_blocked_user_cannot_send_message` |
| `test_blocker_cannot_send_message` | `test_blocker_cannot_send_message` |
| `test_ignored_user_cannot_send_message` | `test_ignored_user_cannot_send_message` |
| `test_message_at_max_length_succeeds` | `test_send_message_at_max_length_succeeds` |
| `test_trashed_contact_cannot_send_message` | `test_trashed_contact_cannot_send_message` |
| `test_both_participants_can_send_messages` | `test_both_participants_can_send_messages` |
| `test_multiple_messages_stored_in_order` | Not critical for Livewire (DB ordering unchanged) |
| `test_message_requires_existing_conversation` | Not applicable — Livewire component receives conversation via mount |
| `test_conversation_shows_messages` | `test_component_renders_with_messages` |
| `test_message_body_cannot_be_null` | Covered by `test_send_message_requires_body` (null and empty both fail required) |

- [ ] **Step 2: Add missing non-participant test**

Append to `tests/Feature/ConversationShowLivewireTest.php`:

```php
    public function test_non_participant_cannot_send_message(): void
    {
        [$userA, $userB, $contact, $conversation] = $this->createContactAndConversation();
        $outsider = User::factory()->create();

        Livewire::actingAs($outsider)
            ->test(ConversationShow::class, [
                'conversation' => $conversation,
                'otherUser' => $userA,
                'isIgnored' => null,
                'isTrashed' => false,
                'isBlocked' => false,
            ])
            ->set('body', 'Hello!')
            ->call('sendMessage')
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }
```

- [ ] **Step 3: Run the new test to verify it passes**

Run:
```bash
php artisan test --compact tests/Feature/ConversationShowLivewireTest.php --filter=test_non_participant_cannot_send_message
```

Expected: PASS — `MessagePolicy::create()` returns false for non-participants.

- [ ] **Step 4: Delete `tests/Feature/MessageTest.php`**

Delete the file `tests/Feature/MessageTest.php`. All its test coverage is now in `ConversationShowLivewireTest.php`.

- [ ] **Step 5: Run full Livewire test suite to verify**

Run:
```bash
php artisan test --compact tests/Feature/ConversationShowLivewireTest.php
```

Expected: All 21 tests PASS.

- [ ] **Step 6: Commit**

```
refactor: migrate MessageTest cases to ConversationShowLivewireTest
```

---

## Task 10: Add Typing Indicators (Alpine/JS)

**Files:**
- Modify: `resources/views/livewire/conversation-show.blade.php`

- [ ] **Step 1: Add Alpine typing indicator to the Livewire Blade view**

Wrap the entire `<div>` root element in the Livewire view with an Alpine component that manages typing state and Echo whispers. Update `resources/views/livewire/conversation-show.blade.php`:

Replace the opening `<div>` tag:

```blade
<div class="relative flex h-full w-full flex-1 flex-col rounded-xl">
```

With:

```blade
<div
    class="relative flex h-full w-full flex-1 flex-col rounded-xl"
    x-data="typingIndicator({{ $conversation->id }}, {{ auth()->id() }})"
    x-init="init()"
>
```

Add a typing indicator display just before the `{{-- Floating Input --}}` comment:

```blade
    {{-- Typing Indicator --}}
    <div
        x-show="isTyping"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="px-4 pb-1"
        aria-live="polite"
    >
        <p class="text-xs text-zinc-400 italic">
            <span x-text="typingUserName"></span> {{ __('is typing...') }}
        </p>
    </div>
```

Add `@input="notifyTyping()"` to the `<flux:input>` element inside the form:

```blade
<flux:input wire:model="body" @input.debounce.500ms="notifyTyping()" placeholder="{{ __('Type a message...') }}" required autocomplete="off" aria-label="{{ __('Message body') }}" />
```

- [ ] **Step 2: Add the Alpine `typingIndicator` component script**

Add this script block at the bottom of the Livewire view, before the closing `</div>`:

```blade
    @script
    <script>
        Alpine.data('typingIndicator', (conversationId, currentUserId) => ({
            isTyping: false,
            typingUserName: '',
            typingTimeout: null,
            channel: null,

            init() {
                this.channel = window.Echo.private('conversation.' + conversationId);

                this.channel.listenForWhisper('typing', (e) => {
                    if (e.userId === currentUserId) return;

                    this.typingUserName = e.name;
                    this.isTyping = true;

                    clearTimeout(this.typingTimeout);
                    this.typingTimeout = setTimeout(() => {
                        this.isTyping = false;
                    }, 3000);
                });
            },

            notifyTyping() {
                if (this.channel) {
                    this.channel.whisper('typing', {
                        userId: currentUserId,
                        name: '{{ auth()->user()->name }}'
                    });
                }
            },

            destroy() {
                clearTimeout(this.typingTimeout);
                if (this.channel) {
                    window.Echo.leave('conversation.' + conversationId);
                }
            }
        }));
    </script>
    @endscript
```

- [ ] **Step 3: Add scroll-to-bottom script**

Add this Livewire event listener script inside the same `@script` block (or as a separate `@script` block):

```blade
    @script
    <script>
        $wire.on('scroll-to-bottom', () => {
            setTimeout(() => {
                const container = document.getElementById('messages-container');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                    window.scrollTo({ top: document.body.scrollHeight, behavior: 'instant' });
                }
            }, 50);
        });

        // Auto-scroll on initial load
        setTimeout(() => {
            const container = document.getElementById('messages-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
                window.scrollTo({ top: document.body.scrollHeight, behavior: 'instant' });
            }
        }, 0);
    </script>
    @endscript
```

- [ ] **Step 4: Build frontend assets**

Run:
```bash
npm run build
```

Expected: Build succeeds.

- [ ] **Step 5: Run all tests to verify nothing is broken**

Run:
```bash
php artisan test --compact tests/Feature/ConversationShowLivewireTest.php && php artisan test --compact tests/Feature/ConversationTest.php
```

Expected: All tests PASS.

- [ ] **Step 6: Run Pint**

Run:
```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```
feat: add typing indicators via Alpine.js and Echo whispers
```

---

## Task 11: Update Dev Workflow

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Add Reverb server to `composer run dev` script**

In `composer.json`, update the `"dev"` script to include the Reverb server. Change:

```json
"dev": [
    "Composer\\Config::disableProcessTimeout",
    "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,logs,vite --kill-others"
]
```

To:

```json
"dev": [
    "Composer\\Config::disableProcessTimeout",
    "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74,#86efac\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan pail --timeout=0\" \"npm run dev\" \"php artisan reverb:start\" --names=server,queue,logs,vite,reverb --kill-others"
]
```

- [ ] **Step 2: Commit**

```
chore: add Reverb server to dev workflow
```

---

## Task 12: Final Verification

- [ ] **Step 1: Run the full test suite**

Run:
```bash
php artisan test --compact
```

Expected: All tests PASS. No failures from removed routes or changed views.

- [ ] **Step 2: Verify no broken route references**

Run:
```bash
php artisan route:list --name=messages
```

Expected: No routes found (the `messages.store` route has been removed).

- [ ] **Step 3: Verify routes are correct**

Run:
```bash
php artisan route:list --except-vendor
```

Expected: No `messages.store` route. Broadcasting auth route should be visible.

- [ ] **Step 4: Build frontend assets one final time**

Run:
```bash
npm run build
```

Expected: Build succeeds.

- [ ] **Step 5: Run Pint on all modified files**

Run:
```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Final commit (if any changes)**

```
chore: final cleanup and verification
```
