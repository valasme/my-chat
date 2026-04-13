<x-layouts::app :title="__('Blocked Users')">
    <div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Blocked Users') }}</flux:heading>
            @if ($blocks->isNotEmpty())
                <div class="flex items-center gap-1" role="group" aria-label="{{ __('Sort options') }}">
                    <flux:button size="sm" :variant="$sort === 'az' ? 'filled' : 'subtle'" :href="route('blocks.index', ['sort' => 'az'])" aria-label="{{ __('Sort A to Z') }}" :aria-pressed="$sort === 'az' ? 'true' : 'false'">A–Z</flux:button>
                    <flux:button size="sm" :variant="$sort === 'za' ? 'filled' : 'subtle'" :href="route('blocks.index', ['sort' => 'za'])" aria-label="{{ __('Sort Z to A') }}" :aria-pressed="$sort === 'za' ? 'true' : 'false'">Z–A</flux:button>
                    @if ($sort)
                        <flux:button size="sm" variant="ghost" icon="x-mark" :href="route('blocks.index')" aria-label="{{ __('Clear sort') }}" />
                    @endif
                </div>
            @endif
        </div>

        @if (session('status'))
            <flux:callout>{{ session('status') }}</flux:callout>
        @endif

        @if ($blocks->isEmpty())
            <flux:text>{{ __('No blocked users.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Blocked') }}</flux:table.column>
                    <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($blocks as $block)
                        <flux:table.row>
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:avatar size="xs" :name="$block->blocked->name" />
                                    <span class="font-medium">{{ $block->blocked->name }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500">{{ $block->blocked->email }}</flux:table.cell>
                            <flux:table.cell class="text-zinc-400"><time datetime="{{ $block->created_at->toIso8601String() }}">{{ $block->created_at->diffForHumans() }}</time></flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end">
                                    <form method="POST" action="{{ route('blocks.destroy', $block) }}" onsubmit="return confirm({{ Js::from(__('Unblock this user?')) }})">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" variant="subtle" size="sm" aria-label="{{ __('Unblock :name', ['name' => $block->blocked->name]) }}">{{ __('Unblock') }}</flux:button>
                                    </form>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            <nav class="mt-4" aria-label="{{ __('Blocked users pagination') }}">
                {{ $blocks->links() }}
            </nav>
        @endif
    </div>
</x-layouts::app>
