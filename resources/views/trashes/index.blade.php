<x-layouts::app :title="__('Trash')">
    <div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Trash') }}</flux:heading>
            @if ($trashes->isNotEmpty())
                <div class="flex items-center gap-1" role="group" aria-label="{{ __('Sort options') }}">
                    <flux:button size="sm" :variant="$sort === 'az' ? 'filled' : 'subtle'" :href="route('trashes.index', ['sort' => 'az'])" aria-label="{{ __('Sort A to Z') }}" :aria-pressed="$sort === 'az' ? 'true' : 'false'">A–Z</flux:button>
                    <flux:button size="sm" :variant="$sort === 'za' ? 'filled' : 'subtle'" :href="route('trashes.index', ['sort' => 'za'])" aria-label="{{ __('Sort Z to A') }}" :aria-pressed="$sort === 'za' ? 'true' : 'false'">Z–A</flux:button>
                    @if ($sort)
                        <flux:button size="sm" variant="ghost" icon="x-mark" :href="route('trashes.index')" aria-label="{{ __('Clear sort') }}" />
                    @endif
                </div>
            @endif
        </div>

        @if (session('status'))
            <flux:callout>{{ session('status') }}</flux:callout>
        @endif

        @if ($trashes->isEmpty())
            <flux:text>{{ __('Trash is empty.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Expires') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($trashes as $trash)
                        @php $otherUser = $trash->contact->getOtherUser(auth()->id()); @endphp
                        <flux:table.row>
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:avatar size="xs" :name="$otherUser->name" />
                                    <span class="font-medium">{{ $otherUser->name }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-400"><time datetime="{{ $trash->expires_at->toIso8601String() }}">{{ $trash->expires_at->format('M d, Y') }}</time></flux:table.cell>
                            <flux:table.cell>
                                @if ($trash->is_quick_delete)
                                    <flux:badge size="sm">{{ __('Quick Delete') }}</flux:badge>
                                @else
                                    <flux:badge size="sm">{{ __('Normal') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('trashes.destroy', $trash) }}" onsubmit="return confirm({{ Js::from(__('Restore this contact from trash?')) }})">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" variant="filled" size="sm" aria-label="{{ __('Restore :name from trash', ['name' => $otherUser->name]) }}">{{ __('Restore') }}</flux:button>
                                    </form>
                                    <form method="POST" action="{{ route('trashes.force-delete', $trash) }}" onsubmit="return confirm({{ Js::from(__('Permanently delete this contact and all conversations?')) }})">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" variant="subtle" size="sm" aria-label="{{ __('Permanently delete :name', ['name' => $otherUser->name]) }}">{{ __('Delete Now') }}</flux:button>
                                    </form>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            <nav class="mt-4" aria-label="{{ __('Trash pagination') }}">
                {{ $trashes->links() }}
            </nav>
        @endif
    </div>
</x-layouts::app>
