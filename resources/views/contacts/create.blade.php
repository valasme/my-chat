{{--
|--------------------------------------------------------------------------
| Add Contact View
|--------------------------------------------------------------------------
|
| Simple form with a single email input for adding contacts. Users
| enter the email address of someone they want to add. The controller
| looks up the user, validates (exists, not self, not duplicate),
| and redirects back with errors or to the index with success.
|
| Route: GET /contacts/create (contacts.create)
|
| @see \App\Http\Controllers\ContactController::create()
| @see \App\Http\Controllers\ContactController::store()
| @see \App\Http\Requests\StoreContactRequest
|
--}}

<x-layouts::app :title="__('Add Contact')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        {{-- Page Header with Back Navigation --}}
        <div class="flex items-center gap-4">
            <flux:button variant="ghost" icon="arrow-left" :href="route('contacts.index')" :aria-label="__('Back')" />
            <flux:heading id="page-title" size="xl">{{ __('Add Contact') }}</flux:heading>
        </div>

        <flux:text>
            {{ __('Search for a user by their email address. If they have an account, you can add them to your contacts.') }}
        </flux:text>

        {{-- Add Contact Form --}}
        <div class="w-full flex-1">
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
                        maxlength="255"
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
