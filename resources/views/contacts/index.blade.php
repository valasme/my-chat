<x-layouts::app :title="__('Contacts')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading id="page-title" size="xl">{{ __('Contacts') }}</flux:heading>

            <flux:button variant="primary" icon="plus" :href="route('contacts.create')">
                {{ __('Add Contact') }}
            </flux:button>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle" dismissible>
                {{ session('success') }}
            </flux:callout>
        @endif

        {{-- Search and Filters --}}
        <form method="GET" action="{{ route('contacts.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <flux:input
                    type="search"
                    name="search"
                    icon="magnifying-glass"
                    placeholder="{{ __('Search by name or email...') }}"
                    :value="$search ?? ''"
                />
            </div>

            <div class="flex gap-2">
                <flux:select name="sort" class="w-36">
                    <option value="name" @selected(($sort ?? 'name') === 'name')>{{ __('Name') }}</option>
                    <option value="email" @selected(($sort ?? '') === 'email')>{{ __('Email') }}</option>
                </flux:select>

                <flux:select name="direction" class="w-28">
                    <option value="asc" @selected(($direction ?? 'asc') === 'asc')>{{ __('A–Z') }}</option>
                    <option value="desc" @selected(($direction ?? '') === 'desc')>{{ __('Z–A') }}</option>
                </flux:select>

                <flux:button type="submit" variant="primary" icon="funnel">
                    {{ __('Filter') }}
                </flux:button>

                @if (request()->hasAny(['search', 'sort', 'direction']))
                    <flux:button :href="route('contacts.index')" variant="ghost" icon="x-mark">
                        {{ __('Clear') }}
                    </flux:button>
                @endif
            </div>
        </form>

        {{-- Contacts Table --}}
        @if ($contacts->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-lg border border-zinc-200 py-16 dark:border-zinc-700">
                <flux:icon name="user-group" class="mb-4 size-12 text-zinc-400" />
                <flux:heading size="lg">{{ __('No contacts found') }}</flux:heading>
                <flux:text class="mt-1">
                    @if ($search)
                        {{ __('Try adjusting your search or filters.') }}
                    @else
                        {{ __('Get started by adding your first contact.') }}
                    @endif
                </flux:text>
                @unless ($search)
                    <flux:button variant="primary" :href="route('contacts.create')" class="mt-4" icon="plus">
                        {{ __('Add Contact') }}
                    </flux:button>
                @endunless
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                                {{ __('Name') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                                {{ __('Email') }}
                            </th>
                            <th scope="col" class="hidden px-4 py-3 text-left text-sm font-semibold text-zinc-700 dark:text-zinc-300 md:table-cell">
                                {{ __('Added') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-right text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                                {{ __('Actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
                        @foreach ($contacts as $contact)
                            <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="contact-{{ $contact->id }}">
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    <a href="{{ route('contacts.show', $contact) }}" class="flex items-center gap-3 underline-offset-4 hover:underline">
                                        <flux:avatar size="sm" :name="$contact->person->name" :initials="$contact->person->initials()" />
                                        {{ $contact->person->name }}
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ $contact->person->email }}
                                </td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400 md:table-cell">
                                    {{ $contact->created_at->format('M d, Y') }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button size="sm" variant="ghost" icon="eye" :href="route('contacts.show', $contact)" :aria-label="__('View')" />
                                        <form method="POST" action="{{ route('contacts.destroy', $contact) }}" class="inline" onsubmit="return confirm('{{ __('Are you sure you want to remove this contact?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <flux:button size="sm" variant="ghost" icon="trash" type="submit" class="text-red-600 hover:text-red-700 dark:text-red-400" :aria-label="__('Remove')" />
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($contacts->hasPages())
                <div class="mt-2">
                    {{ $contacts->links() }}
                </div>
            @endif
        @endif
    </div>
</x-layouts::app>
