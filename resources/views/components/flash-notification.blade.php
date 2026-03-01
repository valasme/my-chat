{{--
|--------------------------------------------------------------------------
| Flash Notification Component
|--------------------------------------------------------------------------
|
| A reusable flash notification that appears when a session message exists.
| Supports "success" and "error" variants with an X dismiss button and
| auto-closes after 5 seconds using Alpine.js.
|
| Usage:
|   <x-flash-notification />
|
| Reads from session keys: 'success', 'error'
|
--}}

@props([
    'duration' => 5000,
])

@if (session('success') || session('error'))
    <div
        x-data="{ visible: true, timer: null }"
        x-init="timer = setTimeout(() => { visible = false }, {{ $duration }})"
        x-show="visible"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="relative"
        role="alert"
    >
        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">
                <div class="flex w-full items-center justify-between gap-4">
                    <span>{{ session('success') }}</span>
                    <button
                        type="button"
                        x-on:click="clearTimeout(timer); visible = false"
                        class="shrink-0 rounded p-0.5 text-zinc-500 transition hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
                        aria-label="{{ __('Dismiss notification') }}"
                    >
                        <flux:icon name="x-mark" class="size-4" />
                    </button>
                </div>
            </flux:callout>
        @endif

        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-triangle">
                <div class="flex w-full items-center justify-between gap-4">
                    <span>{{ session('error') }}</span>
                    <button
                        type="button"
                        x-on:click="clearTimeout(timer); visible = false"
                        class="shrink-0 rounded p-0.5 text-zinc-500 transition hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
                        aria-label="{{ __('Dismiss notification') }}"
                    >
                        <flux:icon name="x-mark" class="size-4" />
                    </button>
                </div>
            </flux:callout>
        @endif
    </div>
@endif
