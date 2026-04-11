<x-layouts::app :title="__('Ignored Users')">
    <div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Ignored Users') }}</flux:heading>
            @if ($ignores->isNotEmpty())
                <div class="flex items-center gap-1">
                    <flux:button size="sm" :variant="$sort === 'az' ? 'filled' : 'subtle'" :href="route('ignores.index', ['sort' => 'az'])">A–Z</flux:button>
                    <flux:button size="sm" :variant="$sort === 'za' ? 'filled' : 'subtle'" :href="route('ignores.index', ['sort' => 'za'])">Z–A</flux:button>
                    @if ($sort)
                        <flux:button size="sm" variant="ghost" icon="x-mark" :href="route('ignores.index')" />
                    @endif
                </div>
            @endif
        </div>

        @if (session('status'))
            <flux:callout>{{ session('status') }}</flux:callout>
        @endif

        @if ($ignores->isEmpty())
            <flux:text>{{ __('No ignored users.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Expires') }}</flux:table.column>
                    <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($ignores as $ignore)
                        <flux:table.row>
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:avatar size="xs" :name="$ignore->ignored->name" />
                                    <span class="font-medium">{{ $ignore->ignored->name }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500">{{ $ignore->ignored->email }}</flux:table.cell>
                            <flux:table.cell class="text-zinc-400">{{ $ignore->expires_at->format('M d, Y H:i') }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end">
                                    <form method="POST" action="{{ route('ignores.destroy', $ignore) }}" onsubmit="return confirm({{ Js::from(__('Cancel ignoring this user?')) }})">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" variant="subtle" size="sm">{{ __('Cancel') }}</flux:button>
                                    </form>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            <div class="mt-4">
                {{ $ignores->links() }}
            </div>
        @endif
    </div>
</x-layouts::app>
