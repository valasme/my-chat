<x-layouts::app :title="__('Notes')">
    <div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Notes') }}</flux:heading>
            <flux:button variant="filled" icon="plus" :href="route('notes.create')" wire:navigate>
                {{ __('Add Note') }}
            </flux:button>
        </div>

        <livewire:note-index />
    </div>
</x-layouts::app>
