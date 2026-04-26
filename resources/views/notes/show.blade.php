<x-layouts::app :title="$note->title">
    <div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl">
        <div class="flex items-center gap-3">
            <flux:button icon="arrow-left" :href="route('notes.index')" wire:navigate variant="subtle" aria-label="{{ __('Back to notes') }}" />
            <flux:heading size="xl">{{ $note->title }}</flux:heading>
        </div>

        @if (session('status'))
            <flux:callout>{{ session('status') }}</flux:callout>
        @endif

        <div class="max-w-2xl space-y-6">
            {{-- Meta --}}
            <div class="flex flex-wrap items-center gap-4 text-sm text-zinc-500">
                <span>{{ __('Created') }}: {{ $note->created_at->diffForHumans() }}</span>
                @if (! $note->updated_at->eq($note->created_at))
                    <span>{{ __('Updated') }}: {{ $note->updated_at->diffForHumans() }}</span>
                @endif
                @if ($note->contact)
                    @php $otherUser = $note->contact->getOtherUser(auth()->id()); @endphp
                    <flux:badge>{{ $otherUser->name }}</flux:badge>
                @endif
            </div>

            {{-- Tags --}}
            @if (! empty($note->tags))
                <div class="flex flex-wrap gap-2" aria-label="{{ __('Tags') }}">
                    @foreach ($note->tags as $tag)
                        <flux:badge size="sm">{{ $tag }}</flux:badge>
                    @endforeach
                </div>
            @endif

            {{-- Body --}}
            <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
                <div class="whitespace-pre-wrap text-sm leading-relaxed">{{ $note->body }}</div>
            </div>

            {{-- Actions --}}
            <div class="flex flex-wrap items-center gap-3" role="group" aria-label="{{ __('Note actions') }}">
                <flux:button variant="filled" icon="pencil" :href="route('notes.edit', $note)" wire:navigate>{{ __('Edit') }}</flux:button>
                <form method="POST" action="{{ route('notes.destroy', $note) }}" onsubmit="return confirm({{ Js::from(__('Move this note to trash?')) }})">
                    @csrf
                    @method('DELETE')
                    <flux:button type="submit" variant="subtle" icon="trash">{{ __('Move to Trash') }}</flux:button>
                </form>
            </div>
        </div>
    </div>
</x-layouts::app>
