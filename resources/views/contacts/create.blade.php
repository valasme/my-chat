<x-layouts::app :title="__('Add Contact')">
    <div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl">
        <div class="flex items-center gap-3">
            <flux:button icon="arrow-left" :href="route('contacts.index')" wire:navigate variant="subtle" aria-label="{{ __('Back to contacts') }}" />
            <flux:heading size="xl">{{ __('Add Contact') }}</flux:heading>
        </div>

        @if (session('status'))
            <flux:callout>{{ session('status') }}</flux:callout>
        @endif

        <form method="POST" action="{{ route('contacts.store') }}" class="max-w-md space-y-6">
            @csrf

            <flux:field>
                <flux:label>{{ __('Email Address') }}</flux:label>
                <flux:input type="email" name="email" :value="old('email')" placeholder="{{ __('Enter their email address') }}" required />
                <flux:error name="email" />
            </flux:field>

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="filled">{{ __('Send Request') }}</flux:button>
                <flux:button variant="subtle" :href="route('contacts.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>