<x-layouts::app :title="$contact->person->name">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <flux:button variant="ghost" icon="arrow-left" :href="route('contacts.index')" :aria-label="__('Back')" />
                <flux:heading id="page-title" size="xl">{{ $contact->person->name }}</flux:heading>
            </div>

            <form method="POST" action="{{ route('contacts.destroy', $contact) }}" onsubmit="return confirm('{{ __('Are you sure you want to remove this contact?') }}')">
                @csrf
                @method('DELETE')
                <flux:button type="submit" variant="danger" icon="trash">
                    {{ __('Remove Contact') }}
                </flux:button>
            </form>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle" dismissible>
                {{ session('success') }}
            </flux:callout>
        @endif

        <div class="w-full max-w-lg rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-6 flex items-center gap-4">
                <flux:avatar size="lg" :name="$contact->person->name" :initials="$contact->person->initials()" />
                <div>
                    <flux:heading size="lg">{{ $contact->person->name }}</flux:heading>
                    <flux:text>{{ $contact->person->email }}</flux:text>
                </div>
            </div>

            <dl class="flex flex-col gap-4">
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
    </div>
</x-layouts::app>
