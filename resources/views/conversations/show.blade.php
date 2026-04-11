<x-layouts::app :title="$otherUser->name">
    <div class="relative flex h-full w-full flex-1 flex-col rounded-xl">
        {{-- Header --}}
        <div class="flex items-center gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <flux:button icon="arrow-left" :href="route('conversations.index')" wire:navigate variant="subtle" size="sm" />
            <flux:avatar size="xs" :name="$otherUser->name" />
            <flux:heading>{{ $otherUser->name }}</flux:heading>
        </div>

        @if ($isIgnored)
            <div class="px-4 pt-3">
                <flux:callout>
                    {{ __('This user is unavailable until :date.', ['date' => $isIgnored->expires_at->format('M d, Y H:i')]) }}
                </flux:callout>
            </div>
        @endif

        @if ($isTrashed)
            <div class="px-4 pt-3">
                <flux:callout>
                    {{ __('This contact is in your trash. Restore to see new messages and send messages.') }}
                </flux:callout>
            </div>
        @endif

        {{-- Messages --}}
        <div id="messages-container" class="flex-1 space-y-3 overflow-y-auto p-4 {{ (! $isTrashed && ! $isIgnored) ? 'pb-20' : '' }}">
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
                        <p class="mt-1 text-xs opacity-50">{{ $msg->created_at->format('H:i') }}</p>
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
                <form method="POST" action="{{ route('messages.store', $conversation) }}" class="flex items-center gap-2">
                    @csrf
                    <div class="flex-1">
                        <flux:input name="body" placeholder="{{ __('Type a message...') }}" required />
                    </div>
                    <flux:button type="submit" variant="filled" icon="arrow-right" />
                </form>
                @error('body')
                    <p class="mt-1 text-xs text-zinc-500">{{ $message }}</p>
                @enderror
            </div>
        @endif
    </div>

    {{-- Auto-scroll to bottom + polling --}}
    <script>
        function initChat() {
            var container = document.getElementById('messages-container');
            if (!container) return;

            container.scrollTop = container.scrollHeight;
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'instant' });

            if (window._chatPollInterval) {
                clearInterval(window._chatPollInterval);
            }

            window._chatPollInterval = setInterval(function () {
                var c = document.getElementById('messages-container');
                if (!c) { clearInterval(window._chatPollInterval); return; }
                fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.text(); })
                    .then(function (html) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(html, 'text/html');
                        var newContainer = doc.getElementById('messages-container');
                        if (newContainer && c && newContainer.innerHTML !== c.innerHTML) {
                            c.innerHTML = newContainer.innerHTML;
                            c.scrollTop = c.scrollHeight;
                            window.scrollTo({ top: document.body.scrollHeight, behavior: 'instant' });
                        }
                    })
                    .catch(function () {});
            }, 5000);
        }

        setTimeout(initChat, 0);
        document.addEventListener('DOMContentLoaded', function() { setTimeout(initChat, 0); });
        document.addEventListener('livewire:navigated', function() { setTimeout(initChat, 0); });
    </script>
</x-layouts::app>
