<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Controls row --}}
    <div class="flex flex-wrap items-center gap-3">
        {{-- Search --}}
        <div class="min-w-48 flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search notes...') }}" icon="magnifying-glass" />
        </div>

        {{-- Filter --}}
        <div class="flex items-center gap-1" role="group" aria-label="{{ __('Filter by type') }}">
            <flux:button size="sm" :variant="$filter === 'all' ? 'filled' : 'subtle'" wire:click="$set('filter', 'all')" :aria-pressed="$filter === 'all' ? 'true' : 'false'">{{ __('All') }}</flux:button>
            <flux:button size="sm" :variant="$filter === 'personal' ? 'filled' : 'subtle'" wire:click="$set('filter', 'personal')" :aria-pressed="$filter === 'personal' ? 'true' : 'false'">{{ __('Personal') }}</flux:button>
            <flux:button size="sm" :variant="$filter === 'contact' ? 'filled' : 'subtle'" wire:click="$set('filter', 'contact')" :aria-pressed="$filter === 'contact' ? 'true' : 'false'">{{ __('Contact') }}</flux:button>
        </div>

        {{-- Sort --}}
        <div class="flex items-center gap-1" role="group" aria-label="{{ __('Sort order') }}">
            <flux:button size="sm" :variant="$sort === 'latest' ? 'filled' : 'subtle'" wire:click="$set('sort', 'latest')" :aria-pressed="$sort === 'latest' ? 'true' : 'false'">{{ __('Latest') }}</flux:button>
            <flux:button size="sm" :variant="$sort === 'az' ? 'filled' : 'subtle'" wire:click="$set('sort', 'az')" :aria-pressed="$sort === 'az' ? 'true' : 'false'">A–Z</flux:button>
            <flux:button size="sm" :variant="$sort === 'za' ? 'filled' : 'subtle'" wire:click="$set('sort', 'za')" :aria-pressed="$sort === 'za' ? 'true' : 'false'">Z–A</flux:button>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex items-center gap-2" role="tablist">
        <flux:button
            wire:click="$set('view', 'active')"
            :variant="$view === 'active' ? 'filled' : 'subtle'"
            size="sm"
            role="tab"
            :aria-selected="$view === 'active' ? 'true' : 'false'"
        >{{ __('Active') }}</flux:button>
        <flux:button
            wire:click="$set('view', 'trashed')"
            :variant="$view === 'trashed' ? 'filled' : 'subtle'"
            size="sm"
            role="tab"
            :aria-selected="$view === 'trashed' ? 'true' : 'false'"
        >{{ __('Trash') }}</flux:button>
    </div>

    @if (session('status'))
        <flux:callout>{{ session('status') }}</flux:callout>
    @endif

    {{-- Table --}}
    @if ($notes->isEmpty())
        <flux:text>
            @if ($view === 'trashed')
                {{ __('No notes in trash.') }}
            @elseif ($search !== '')
                {{ __('No notes match your search.') }}
            @else
                {{ __('No notes yet. Create one to get started.') }}
            @endif
        </flux:text>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column>{{ __('Contact') }}</flux:table.column>
                <flux:table.column>{{ __('Created') }}</flux:table.column>
                <flux:table.column>{{ __('Updated') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($notes as $note)
                    <flux:table.row wire:key="{{ $note->id }}">
                        <flux:table.cell class="font-medium">
                            @if ($view === 'active')
                                <a href="{{ route('notes.show', $note) }}" class="hover:underline" wire:navigate>{{ $note->title }}</a>
                            @else
                                {{ $note->title }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">
                            @if ($note->contact)
                                {{ $note->contact->getOtherUser(auth()->id())->name }}
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            {{ $note->created_at->diffForHumans() }}
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            {{ $note->updated_at->diffForHumans() }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center justify-end gap-2">
                                @if ($view === 'active')
                                    <flux:button size="sm" variant="subtle" :href="route('notes.show', $note)" wire:navigate>{{ __('View') }}</flux:button>
                                    <flux:button size="sm" variant="subtle" :href="route('notes.edit', $note)" wire:navigate>{{ __('Edit') }}</flux:button>
                                    <form method="POST" action="{{ route('notes.destroy', $note) }}" onsubmit="return confirm({{ Js::from(__('Move this note to trash?')) }})">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" size="sm" variant="subtle">{{ __('Trash') }}</flux:button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('notes.restore', $note->id) }}">
                                        @csrf
                                        <flux:button type="submit" size="sm" variant="subtle">{{ __('Restore') }}</flux:button>
                                    </form>
                                    <form method="POST" action="{{ route('notes.force-delete', $note->id) }}" onsubmit="return confirm({{ Js::from(__('Permanently delete this note? This cannot be undone.')) }})">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" size="sm" variant="subtle">{{ __('Delete') }}</flux:button>
                                    </form>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <nav class="mt-4" aria-label="{{ __('Notes pagination') }}">
            {{ $notes->links() }}
        </nav>
    @endif
</div>
