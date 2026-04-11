<x-layouts::app :title="$otherUser->name">
    <div class="flex h-full w-full flex-1 flex-col gap-8 rounded-xl">
        <div class="flex items-center gap-3">
            <flux:button icon="arrow-left" :href="route('contacts.index')" wire:navigate variant="subtle" />
            <flux:heading size="xl">{{ $otherUser->name }}</flux:heading>
        </div>

        @if (session('status'))
            <flux:callout>{{ session('status') }}</flux:callout>
        @endif

        <div class="flex items-center gap-4 rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:avatar size="lg" :name="$otherUser->name" />
            <div>
                <flux:heading size="lg">{{ $otherUser->name }}</flux:heading>
                <flux:text size="sm" class="text-zinc-500">{{ $otherUser->email }}</flux:text>
                <flux:badge size="sm" class="mt-1">{{ ucfirst($contact->status) }}</flux:badge>
            </div>
        </div>

        @if ($contact->status === 'accepted')
            <div class="flex flex-wrap items-center gap-3">
                <flux:button variant="filled" icon="chat-bubble-left-right" :href="$conversation ? route('conversations.show', $conversation) : route('conversations.index')" wire:navigate>
                    {{ __('Message') }}
                </flux:button>

                <flux:modal.trigger :name="'block-' . $contact->id">
                    <flux:button variant="subtle" icon="no-symbol">{{ __('Block') }}</flux:button>
                </flux:modal.trigger>

                <flux:modal.trigger :name="'ignore-' . $contact->id">
                    <flux:button variant="subtle" icon="clock">{{ __('Ignore') }}</flux:button>
                </flux:modal.trigger>

                <flux:modal.trigger :name="'trash-' . $contact->id">
                    <flux:button variant="subtle" icon="trash">{{ __('Move to Trash') }}</flux:button>
                </flux:modal.trigger>

                <form method="POST" action="{{ route('contacts.destroy', $contact) }}" onsubmit="return confirm({{ Js::from(__('Delete this contact? This will permanently delete the contact and all conversations for both users.')) }})">
                    @csrf
                    @method('DELETE')
                    <flux:button type="submit" variant="subtle" icon="x-mark">{{ __('Delete Contact') }}</flux:button>
                </form>
            </div>

            {{-- Block Confirmation Modal --}}
            <flux:modal :name="'block-' . $contact->id">
                <flux:heading>{{ __('Block :name?', ['name' => $otherUser->name]) }}</flux:heading>
                <flux:text class="mt-2">{{ __('Blocking will permanently delete the contact and all conversations between you.') }}</flux:text>
                <div class="mt-4 flex items-center gap-3">
                    <form method="POST" action="{{ route('blocks.store') }}">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $otherUser->id }}">
                        <flux:button type="submit" variant="filled">{{ __('Block') }}</flux:button>
                    </form>
                </div>
            </flux:modal>

            {{-- Ignore Modal --}}
            <flux:modal :name="'ignore-' . $contact->id">
                <flux:heading>{{ __('Ignore :name', ['name' => $otherUser->name]) }}</flux:heading>
                <flux:text class="mt-2">{{ __('Choose how long to ignore this contact.') }}</flux:text>
                <form method="POST" action="{{ route('ignores.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $otherUser->id }}">
                    <flux:field>
                        <flux:label>{{ __('Duration') }}</flux:label>
                        <flux:select name="duration">
                            <flux:select.option value="1h">{{ __('1 hour') }}</flux:select.option>
                            <flux:select.option value="8h">{{ __('8 hours') }}</flux:select.option>
                            <flux:select.option value="24h">{{ __('24 hours') }}</flux:select.option>
                            <flux:select.option value="3d">{{ __('3 days') }}</flux:select.option>
                            <flux:select.option value="7d">{{ __('7 days') }}</flux:select.option>
                            <flux:select.option value="custom">{{ __('Custom date') }}</flux:select.option>
                        </flux:select>
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Custom End Date') }}</flux:label>
                        <flux:input type="datetime-local" name="expires_at" />
                        <flux:error name="expires_at" />
                    </flux:field>
                    <flux:button type="submit" variant="filled">{{ __('Ignore') }}</flux:button>
                </form>
            </flux:modal>

            {{-- Trash Modal --}}
            <flux:modal :name="'trash-' . $contact->id">
                <flux:heading>{{ __('Move :name to Trash?', ['name' => $otherUser->name]) }}</flux:heading>
                <flux:text class="mt-2">{{ __('You won\'t see their messages until you restore them. Choose a period or quick-delete all messages.') }}</flux:text>
                <form method="POST" action="{{ route('trashes.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="contact_id" value="{{ $contact->id }}">
                    <flux:field>
                        <flux:label>{{ __('Auto-delete after') }}</flux:label>
                        <flux:select name="duration">
                            <flux:select.option value="7d">{{ __('7 days') }}</flux:select.option>
                            <flux:select.option value="14d">{{ __('14 days') }}</flux:select.option>
                            <flux:select.option value="30d">{{ __('30 days') }}</flux:select.option>
                            <flux:select.option value="60d">{{ __('60 days') }}</flux:select.option>
                            <flux:select.option value="custom">{{ __('Custom date') }}</flux:select.option>
                        </flux:select>
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Custom Date') }}</flux:label>
                        <flux:input type="datetime-local" name="expires_at" />
                        <flux:error name="expires_at" />
                    </flux:field>
                    <div class="flex items-center gap-3">
                        <flux:button type="submit" variant="filled">{{ __('Move to Trash') }}</flux:button>
                        <flux:button type="submit" name="is_quick_delete" value="1" variant="subtle" onclick="return confirm({{ Js::from(__('Quick delete? This will erase all messages immediately.')) }})">{{ __('Quick Delete') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endif
    </div>
</x-layouts::app>