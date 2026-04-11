<x-layouts::app :title="__('Blocked Users')">
    <div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Blocked Users') }}</flux:heading>
            @if ($blocks->isNotEmpty())
                <div class="flex items-center gap-1">
                    <flux:button size="sm" :variant="$sort === 'az' ? 'filled' : 'subtle'" :href="route('blocks.index', ['sort' => 'az'])">A–Z</flux:button>
                    <flux:button size="sm" :variant="$sort === 'za' ? 'filled' : 'subtle'" :href="route('blocks.index', ['sort' => 'za'])">Z–A</flux:button>
                    @if ($sort)
                        <flux:button size="sm" variant="ghost" icon="x-mark" :href="route('blocks.index')" />
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
                            <flux:table.cell class="text-zinc-400">{{ $block->created_at->diffForHumans() }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end">
                                    <form method="POST" action="{{ route('blocks.destroy', $block) }}" onsubmit="return confirm({{ Js::from(__('Unblock this user?')) }})">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" variant="subtle" size="sm">{{ __('Unblock') }}</flux:button>
                                    </form>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            <div class="mt-4">
                {{ $blocks->links() }}
            </div>
        @endif
    </div>
</x-layouts::app>
