<x-layouts::app :title="__('New Note')">
    <div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl">
        <div class="flex items-center gap-3">
            <flux:button icon="arrow-left" :href="route('notes.index')" wire:navigate variant="subtle" aria-label="{{ __('Back to notes') }}" />
            <flux:heading size="xl">{{ __('New Note') }}</flux:heading>
        </div>

        @if (session('status'))
            <flux:callout>{{ session('status') }}</flux:callout>
        @endif

        <form method="POST" action="{{ route('notes.store') }}" class="max-w-2xl space-y-6">
            @csrf

            <flux:field>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:input name="title" :value="old('title')" required autofocus />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Body') }}</flux:label>
                <flux:textarea name="body" rows="8" required>{{ old('body') }}</flux:textarea>
                <flux:error name="body" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Tags') }}</flux:label>
                <flux:description>{{ __('Comma-separated, e.g. work, important') }}</flux:description>
                <flux:input name="tags" :value="implode(', ', (array) old('tags', []))" placeholder="{{ __('work, personal, important') }}" />
                <flux:error name="tags" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Contact') }}</flux:label>
                <flux:description>{{ __('Optionally associate this note with a contact.') }}</flux:description>
                <flux:select name="contact_id">
                    <flux:select.option value="">{{ __('None (personal note)') }}</flux:select.option>
                    @foreach ($contacts as $contact)
                        @php $otherUser = $contact->getOtherUser(auth()->id()); @endphp
                        <flux:select.option value="{{ $contact->id }}" :selected="old('contact_id') == $contact->id">
                            {{ $otherUser->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="contact_id" />
            </flux:field>

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="filled">{{ __('Create Note') }}</flux:button>
                <flux:button variant="subtle" :href="route('notes.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
