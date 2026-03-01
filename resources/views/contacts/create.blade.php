<x-layouts::app :title="__('Add Contact')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div class="flex items-center gap-4">
            <flux:button variant="ghost" icon="arrow-left" :href="route('contacts.index')" :aria-label="__('Back')" />
            <flux:heading id="page-title" size="xl">{{ __('Add Contact') }}</flux:heading>
        </div>

        <flux:text class="max-w-lg">
            {{ __('Search for a user by their email address. If they have an account, you can add them to your contacts.') }}
        </flux:text>

        <div class="w-full max-w-lg">
            <form method="POST" action="{{ route('contacts.store') }}" class="flex flex-col gap-6">
                @csrf

                <flux:field>
                    <flux:label>{{ __('Email Address') }}</flux:label>
                    <flux:input
                        type="email"
                        name="email"
                        :value="old('email')"
                        required
                        autofocus
                        placeholder="{{ __('Enter their email address...') }}"
                        icon="magnifying-glass"
                    />
                    <flux:error name="email" />
                </flux:field>

                <div class="flex items-center gap-3">
                    <flux:button type="submit" variant="primary" icon="user-plus">
                        {{ __('Add Contact') }}
                    </flux:button>
                    <flux:button :href="route('contacts.index')" variant="ghost">
                        {{ __('Cancel') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
