<x-layouts::app :title="__('Conversations')">
    <div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Conversations') }}</flux:heading>
            @if ($conversations->isNotEmpty())
                <div class="flex items-center gap-1" role="group" aria-label="{{ __('Sort options') }}">
                    <flux:button size="sm" :variant="$sort === 'az' ? 'filled' : 'subtle'" :href="route('conversations.index', ['sort' => 'az'])" aria-label="{{ __('Sort A to Z') }}" :aria-pressed="$sort === 'az' ? 'true' : 'false'">A–Z</flux:button>
                    <flux:button size="sm" :variant="$sort === 'za' ? 'filled' : 'subtle'" :href="route('conversations.index', ['sort' => 'za'])" aria-label="{{ __('Sort Z to A') }}" :aria-pressed="$sort === 'za' ? 'true' : 'false'">Z–A</flux:button>
                    @if ($sort)
                        <flux:button size="sm" variant="ghost" icon="x-mark" :href="route('conversations.index')" aria-label="{{ __('Clear sort') }}" />
                    @endif
                </div>
            @endif
        </div>

        @if ($conversations->isEmpty())
            <flux:text>{{ __('No conversations yet. Accept a contact request to start chatting.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Contact') }}</flux:table.column>
                    <flux:table.column>{{ __('Last Message') }}</flux:table.column>
                    <flux:table.column class="text-right">{{ __('Time') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($conversations as $conversation)
                        @php
                            $otherUser = $conversation->getOtherUser(auth()->id());
                            $lastMessage = $conversation->messages->first();
                        @endphp
                        <flux:table.row>
                            <flux:table.cell>
                                <a href="{{ route('conversations.show', $conversation) }}" class="flex items-center gap-2 hover:underline" wire:navigate aria-label="{{ __('Open conversation with :name', ['name' => $otherUser->name]) }}">
                                    <flux:avatar size="xs" :name="$otherUser->name" />
                                    <span class="font-medium">{{ $otherUser->name }}</span>
                                </a>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500">
                                @if ($lastMessage)
                                    {{ Str::limit($lastMessage->body, 50) }}
                                @else
                                    {{ __('No messages yet') }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-right text-zinc-400">
                                @if ($lastMessage)
                                    <time datetime="{{ $lastMessage->created_at->toIso8601String() }}">{{ $lastMessage->created_at->diffForHumans() }}</time>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            <nav class="mt-4" aria-label="{{ __('Conversations pagination') }}">
                {{ $conversations->links() }}
            </nav>
        @endif
    </div>
</x-layouts::app>
