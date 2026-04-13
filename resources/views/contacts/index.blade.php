<x-layouts::app :title="__('Contacts')">
    <div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Contacts') }}</flux:heading>
            <flux:button variant="filled" icon="plus" :href="route('contacts.create')" wire:navigate>
                {{ __('Add Contact') }}
            </flux:button>
        </div>

        @if (session('status'))
            <flux:callout>{{ session('status') }}</flux:callout>
        @endif

        {{-- Incoming Pending Requests --}}
        @if ($incoming->isNotEmpty())
            <section aria-labelledby="contacts-incoming-heading">
                <flux:heading size="lg" class="mb-3" id="contacts-incoming-heading">{{ __('Incoming Requests') }}</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Email') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($incoming as $contact)
                            <flux:table.row>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:avatar size="xs" :name="$contact->user->name" />
                                        <span class="font-medium">{{ $contact->user->name }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-500">{{ $contact->user->email }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center justify-end gap-2">
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

        {{-- Outgoing Pending Requests --}}
        @if ($outgoing->isNotEmpty())
            <section aria-labelledby="contacts-outgoing-heading">
                <flux:heading size="lg" class="mb-3" id="contacts-outgoing-heading">{{ __('Pending Requests') }}</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Email') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($outgoing as $contact)
                            <flux:table.row>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:avatar size="xs" :name="$contact->contactUser->name" />
                                        <span class="font-medium">{{ $contact->contactUser->name }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-500">{{ $contact->contactUser->email }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm">{{ __('Pending') }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex justify-end">
                                        <form method="POST" action="{{ route('contacts.destroy', $contact) }}" onsubmit="return confirm({{ Js::from(__('Cancel this request?')) }})">
                                            @csrf
                                            @method('DELETE')
                                            <flux:button type="submit" variant="subtle" size="sm" aria-label="{{ __('Cancel request to :name', ['name' => $contact->contactUser->name]) }}">{{ __('Cancel') }}</flux:button>
                                        </form>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </section>
        @endif

        {{-- Accepted Contacts --}}
        <section aria-labelledby="contacts-accepted-heading">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg" id="contacts-accepted-heading">{{ __('Your Contacts') }}</flux:heading>
                @if ($accepted->isNotEmpty())
                    <div class="flex items-center gap-1" role="group" aria-label="{{ __('Sort options') }}">
                        <flux:button size="sm" :variant="$sort === 'az' ? 'filled' : 'subtle'" :href="route('contacts.index', ['sort' => 'az'])" aria-label="{{ __('Sort A to Z') }}" :aria-pressed="$sort === 'az' ? 'true' : 'false'">A–Z</flux:button>
                        <flux:button size="sm" :variant="$sort === 'za' ? 'filled' : 'subtle'" :href="route('contacts.index', ['sort' => 'za'])" aria-label="{{ __('Sort Z to A') }}" :aria-pressed="$sort === 'za' ? 'true' : 'false'">Z–A</flux:button>
                        @if ($sort)
                            <flux:button size="sm" variant="ghost" icon="x-mark" :href="route('contacts.index')" aria-label="{{ __('Clear sort') }}" />
                        @endif
                    </div>
                @endif
            </div>
            @if ($accepted->isEmpty())
                <flux:text>{{ __('No contacts yet. Send a request to get started.') }}</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Email') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($accepted as $contact)
                            @php $otherUser = $contact->getOtherUser(auth()->id()); @endphp
                            <flux:table.row>
                                <flux:table.cell>
                                    <a href="{{ route('contacts.show', $contact) }}" class="flex items-center gap-2 hover:underline" wire:navigate aria-label="{{ __('View contact :name', ['name' => $otherUser->name]) }}">
                                        <flux:avatar size="xs" :name="$otherUser->name" />
                                        <span class="font-medium">{{ $otherUser->name }}</span>
                                    </a>
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-500">{{ $otherUser->email }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex justify-end">
                                        <flux:button variant="subtle" size="sm" :href="route('contacts.show', $contact)" wire:navigate>{{ __('View') }}</flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
                <nav class="mt-4" aria-label="{{ __('Contacts pagination') }}">
                    {{ $accepted->links() }}
                </nav>
            @endif
        </section>
    </div>
</x-layouts::app>