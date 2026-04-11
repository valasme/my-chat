<x-layouts::app :title="__('Conversations')">
    <div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Conversations') }}</flux:heading>
            @if ($conversations->isNotEmpty())
                <div class="flex items-center gap-1">
                    <flux:button size="sm" :variant="$sort === 'az' ? 'filled' : 'subtle'" :href="route('conversations.index', ['sort' => 'az'])">A–Z</flux:button>
                    <flux:button size="sm" :variant="$sort === 'za' ? 'filled' : 'subtle'" :href="route('conversations.index', ['sort' => 'za'])">Z–A</flux:button>
                    @if ($sort)
                        <flux:button size="sm" variant="ghost" icon="x-mark" :href="route('conversations.index')" />
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
                                <a href="{{ route('conversations.show', $conversation) }}" class="flex items-center gap-2 hover:underline" wire:navigate>
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
                                    {{ $lastMessage->created_at->diffForHumans() }}
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            <div class="mt-4">
                {{ $conversations->links() }}
            </div>
        @endif
    </div>
</x-layouts::app>
