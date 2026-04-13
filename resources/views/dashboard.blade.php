<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl">
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4" role="group" aria-label="{{ __('Account statistics') }}">
            <a href="{{ route('contacts.index') }}" class="rounded-lg border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" aria-label="{{ trans_choice(':count contact|:count contacts', $contactsCount) }}">
                <div class="flex items-center gap-3">
                    <flux:icon name="users" class="text-zinc-400" aria-hidden="true" />
                    <div>
                        <p class="text-2xl font-semibold" aria-hidden="true">{{ $contactsCount }}</p>
                        <p class="text-sm text-zinc-500">{{ __('Contacts') }}</p>
                    </div>
                </div>
            </a>
            <a href="{{ route('conversations.index') }}" class="rounded-lg border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" aria-label="{{ trans_choice(':count conversation|:count conversations', $conversationsCount) }}">
                <div class="flex items-center gap-3">
                    <flux:icon name="chat-bubble-left-right" class="text-zinc-400" aria-hidden="true" />
                    <div>
                        <p class="text-2xl font-semibold" aria-hidden="true">{{ $conversationsCount }}</p>
                        <p class="text-sm text-zinc-500">{{ __('Conversations') }}</p>
                    </div>
                </div>
            </a>
            <a href="{{ route('blocks.index') }}" class="rounded-lg border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" aria-label="{{ trans_choice(':count blocked user|:count blocked users', $blocksCount) }}">
                <div class="flex items-center gap-3">
                    <flux:icon name="no-symbol" class="text-zinc-400" aria-hidden="true" />
                    <div>
                        <p class="text-2xl font-semibold" aria-hidden="true">{{ $blocksCount }}</p>
                        <p class="text-sm text-zinc-500">{{ __('Blocked') }}</p>
                    </div>
                </div>
            </a>
            <a href="{{ route('ignores.index') }}" class="rounded-lg border border-zinc-200 p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" aria-label="{{ trans_choice(':count ignored user|:count ignored users', $ignoresCount) }}">
                <div class="flex items-center gap-3">
                    <flux:icon name="clock" class="text-zinc-400" aria-hidden="true" />
                    <div>
                        <p class="text-2xl font-semibold" aria-hidden="true">{{ $ignoresCount }}</p>
                        <p class="text-sm text-zinc-500">{{ __('Ignored') }}</p>
                    </div>
                </div>
            </a>
        </div>

        {{-- Incoming Pending Requests --}}
        @if ($incomingRequests->isNotEmpty())
            <section aria-labelledby="dashboard-incoming-requests-heading">
                <div class="mb-3 flex items-center justify-between">
                    <flux:heading size="lg" id="dashboard-incoming-requests-heading">{{ __('Incoming Requests') }}</flux:heading>
                    @if ($incomingTotal > 5)
                        <flux:button variant="subtle" size="sm" :href="route('contacts.index')" wire:navigate aria-label="{{ __('View all incoming requests') }}">
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
                                            <flux:button type="submit" variant="filled" size="sm" aria-label="{{ __('Accept request from :name', ['name' => $contact->user->name]) }}">{{ __('Accept') }}</flux:button>
                                        </form>
                                        <form method="POST" action="{{ route('contacts.update', $contact) }}" onsubmit="return confirm({{ Js::from(__('Decline this request?')) }})">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="action" value="decline">
                                            <flux:button type="submit" variant="subtle" size="sm" aria-label="{{ __('Decline request from :name', ['name' => $contact->user->name]) }}">{{ __('Decline') }}</flux:button>
                                        </form>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </section>
        @endif

        {{-- Recent Conversations --}}
        <section aria-labelledby="dashboard-recent-conversations-heading">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg" id="dashboard-recent-conversations-heading">{{ __('Recent Conversations') }}</flux:heading>
                <flux:button variant="subtle" size="sm" :href="route('conversations.index')" wire:navigate aria-label="{{ __('View all conversations') }}">
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
            @endif
        </section>

        {{-- Expiring Soon --}}
        @if ($expiringSoon->isNotEmpty())
            <section aria-labelledby="dashboard-expiring-soon-heading">
                <flux:heading size="lg" class="mb-3" id="dashboard-expiring-soon-heading">{{ __('Expiring Soon') }}</flux:heading>
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
                                    <a href="{{ $item->link }}" class="font-medium hover:underline" wire:navigate aria-label="{{ __('View :name', ['name' => $item->name]) }}">{{ $item->name }}</a>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm">{{ __($item->type) }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-400"><time datetime="{{ $item->expires_at->toIso8601String() }}">{{ $item->expires_at->diffForHumans() }}</time></flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </section>
        @endif
    </div>
</x-layouts::app>
