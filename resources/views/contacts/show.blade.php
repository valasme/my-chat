{{--
|--------------------------------------------------------------------------
| Contact Detail View
|--------------------------------------------------------------------------
|
| Displays the full profile of a single contact including their avatar,
| name, email (with mailto link), account creation date, and when they
| were added to the user's contacts. Includes a "Remove Contact" button
| with a JavaScript confirmation dialog.
|
| Route: GET /contacts/{contact} (contacts.show)
|
| @see \App\Http\Controllers\ContactController::show()
| @see \App\Http\Controllers\ContactController::destroy()
|
| Variables:
|   @var \App\Models\Contact $contact  The contact with person() eager-loaded.
|
--}}

<x-layouts::app :title="$contact->person->name">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        {{-- Page Header with Back Navigation and Remove Button --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <flux:button variant="ghost" icon="arrow-left" :href="route('contacts.index')" :aria-label="__('Back')" />
                <flux:heading id="page-title" size="xl">{{ $contact->person->name }}</flux:heading>
            </div>

            <flux:modal.trigger name="delete-contact">
                <flux:button variant="danger" icon="trash">
                    {{ __('Remove Contact') }}
                </flux:button>
            </flux:modal.trigger>
        </div>

        {{-- Flash Notification (auto-dismisses after 5s, closeable with X) --}}
        <x-flash-notification />

        {{-- Contact Profile Card --}}
        <div class="flex-1 rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            {{-- Avatar and Name Header --}}
            <div class="mb-6 flex items-center gap-4">
                <flux:avatar size="lg" :name="$contact->person->name" :initials="$contact->person->initials()" />
                <div>
                    <flux:heading size="lg">{{ $contact->person->name }}</flux:heading>
                    <flux:text>{{ $contact->person->email }}</flux:text>
                </div>
            </div>

            {{-- Contact Details --}}
            <dl class="grid gap-6 sm:grid-cols-3">
                <div>
                    <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Email') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                        <a href="mailto:{{ $contact->person->email }}" class="underline-offset-4 hover:underline">{{ $contact->person->email }}</a>
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Member Since') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $contact->person->created_at->format('M d, Y') }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Added to Contacts') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $contact->created_at->format('M d, Y \a\t g:i A') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Delete Confirmation Modal (rendered outside main content flow) --}}
        <flux:modal name="delete-contact" class="max-w-lg">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Remove Contact?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('You are about to remove :name from your contacts. This action cannot be undone.', ['name' => $contact->person->name]) }}
                    </flux:subheading>
                </div>

                <div class="flex justify-end space-x-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <form method="POST" action="{{ route('contacts.destroy', $contact) }}">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" variant="danger" icon="trash">{{ __('Remove') }}</flux:button>
                    </form>
                </div>
            </div>
        </flux:modal>
    </div>
</x-layouts::app>
