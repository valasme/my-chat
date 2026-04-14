<x-layouts::app :title="$otherUser->name">
    <livewire:conversation-show
        :conversation="$conversation"
        :other-user="$otherUser"
        :is-ignored="$isIgnored"
        :is-trashed="$isTrashed"
        :is-blocked="$isBlocked"
    />
</x-layouts::app>
