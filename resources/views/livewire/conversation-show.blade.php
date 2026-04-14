<div
    class="relative flex h-full w-full flex-1 flex-col rounded-xl"
    x-data="typingIndicator({{ $conversation->id }}, {{ auth()->id() }})"
    x-init="init()"
>
    {{-- Header --}}
    <header class="flex items-center gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
        <flux:button icon="arrow-left" :href="route('conversations.index')" wire:navigate variant="subtle" size="sm" aria-label="{{ __('Back to conversations') }}" />
        <flux:avatar size="xs" :name="$otherUser->name" />
        <flux:heading>{{ $otherUser->name }}</flux:heading>
    </header>

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
        class="flex-1 space-y-3 overflow-y-auto p-4 {{ ! $isTrashed ? 'pb-20' : '' }}"
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
        <p class="text-xs italic text-zinc-400">
            <span x-text="typingUserName"></span> {{ __('is typing...') }}
        </p>
    </div>

    {{-- Floating Bottom Bar --}}
    @if ($isIgnored)
        <div class="sticky bottom-0 z-10 border-t border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900" role="alert">
            <div class="flex items-center justify-between gap-3">
                <flux:text class="text-sm text-zinc-500">
                    {{ __('This user is unavailable until :date.', ['date' => $isIgnored->expires_at->format('M d, Y H:i')]) }}
                </flux:text>
                <flux:button variant="subtle" icon="arrow-left" :href="route('conversations.index')" wire:navigate size="sm">
                    {{ __('Go back') }}
                </flux:button>
            </div>
        </div>
    @elseif (! $isTrashed)
        <div class="sticky bottom-0 z-10 border-t border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <form wire:submit="sendMessage" class="flex items-center gap-2">
                <div class="flex-1">
                    <flux:input wire:model="body" @input.debounce.500ms="notifyTyping()" placeholder="{{ __('Type a message...') }}" required autocomplete="off" aria-label="{{ __('Message body') }}" />
                </div>
                <flux:button type="submit" variant="filled" icon="arrow-right" aria-label="{{ __('Send message') }}" />
            </form>
            @error('body')
                <p class="mt-1 text-xs text-zinc-500" role="alert">{{ $message }}</p>
            @enderror
        </div>
    @endif

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
                        name: @json(auth()->user()->name)
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
</div>
