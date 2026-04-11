<x-layouts::app :title="__('Edit Contact')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <flux:heading size="xl">{{ __('Edit Contact') }}</flux:heading>
        <flux:text>{{ __('Contact editing is handled from the contact detail page.') }}</flux:text>
        <flux:button :href="route('contacts.index')" wire:navigate>{{ __('Back to Contacts') }}</flux:button>
    </div>
</x-layouts::app>