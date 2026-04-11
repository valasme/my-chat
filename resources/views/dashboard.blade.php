<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl">
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <a href="{{ route('contacts.index') }}" class="rounded-lg border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                <div class="flex items-center gap-3">
                    <flux:icon name="users" class="text-zinc-400" />
                    <div>
                        <p class="text-2xl font-semibold">{{ $contactsCount }}</p>
                        <p class="text-sm text-zinc-500">{{ __('Contacts') }}</p>
                    </div>
                </div>
            </a>
            <a href="{{ route('conversations.index') }}" class="rounded-lg border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                <div class="flex items-center gap-3">
                    <flux:icon name="chat-bubble-left-right" class="text-zinc-400" />
                    <div>
                        <p class="text-2xl font-semibold">{{ $conversationsCount }}</p>
                        <p class="text-sm text-zinc-500">{{ __('Conversations') }}</p>
                    </div>
                </div>
            </a>
            <a href="{{ route('blocks.index') }}" class="rounded-lg border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                <div class="flex items-center gap-3">
                    <flux:icon name="no-symbol" class="text-zinc-400" />
                    <div>
                        <p class="text-2xl font-semibold">{{ $blocksCount }}</p>
                        <p class="text-sm text-zinc-500">{{ __('Blocked') }}</p>
                    </div>
                </div>
            </a>
            <a href="{{ route('ignores.index') }}" class="rounded-lg border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                <div class="flex items-center gap-3">
                    <flux:icon name="clock" class="text-zinc-400" />
                    <div>
                        <p class="text-2xl font-semibold">{{ $ignoresCount }}</p>
                        <p class="text-sm text-zinc-500">{{ __('Ignored') }}</p>
                    </div>
                </div>
            </a>
        </div>

        {{-- Incoming Pending Requests --}}
        @if ($incomingRequests->isNotEmpty())
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Incoming Requests') }}</flux:heading>
                    @if ($incomingTotal > 5)
                        <flux:button variant="subtle" size="sm" :href="route('contacts.index')" wire:navigate>
                            {{ __('View all') }} →
                        </flux:button>
                    @endif
                </div>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Email') }}</flux:table.column>
                        <flux:table.column>{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($incomingRequests as $contact)
                            <flux:table.row>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:avatar size="xs" :name="$contact->user->name" />
                                        <span class="font-medium">{{ $contact->user->name }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-500">{{ $contact->user->email }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('contacts.update', $contact) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="action" value="accept">
                                            <flux:button type="submit" variant="filled" size="sm">{{ __('Accept') }}</flux:button>
                                        </form>
                                        <form method="POST" action="{{ route('contacts.update', $contact) }}" onsubmit="return confirm({{ Js::from(__('Decline this request?')) }})">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="action" value="decline">
                                            <flux:button type="submit" variant="subtle" size="sm">{{ __('Decline') }}</flux:button>
                                        </form>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif

        {{-- Recent Conversations --}}
        <div>
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Recent Conversations') }}</flux:heading>
                <flux:button variant="subtle" size="sm" :href="route('conversations.index')" wire:navigate>
                    {{ __('View all') }} →
                </flux:button>
            </div>
            @if ($recentConversations->isEmpty())
                <flux:text>{{ __('No conversations yet.') }}</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Contact') }}</flux:table.column>
                        <flux:table.column>{{ __('Last Message') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Time') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($recentConversations as $conversation)
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
            @endif
        </div>

        {{-- Expiring Soon --}}
        @if ($expiringSoon->isNotEmpty())
            <div>
                <flux:heading size="lg" class="mb-3">{{ __('Expiring Soon') }}</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Contact') }}</flux:table.column>
                        <flux:table.column>{{ __('Type') }}</flux:table.column>
                        <flux:table.column>{{ __('Expires') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($expiringSoon as $item)
                            <flux:table.row>
                                <flux:table.cell>
                                    <a href="{{ $item->link }}" class="font-medium hover:underline" wire:navigate>{{ $item->name }}</a>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm">{{ __($item->type) }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-400">{{ $item->expires_at->diffForHumans() }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </div>
</x-layouts::app>
