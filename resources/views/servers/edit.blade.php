<x-layouts.app title="Edit License Server">
    <x-page-header title="Edit License Server" icon="server" :subtitle="$server->name"
        :back="['href' => route('servers.show', $server), 'label' => $server->name]" />

    <x-card>
        <form method="POST" action="{{ route('servers.update', $server) }}" class="space-y-5">
            @csrf @method('PUT')
            @include('servers._fields', ['server' => $server])
            <div class="flex items-center justify-end gap-2 pt-1">
                <x-button variant="secondary" href="{{ route('servers.show', $server) }}">Cancel</x-button>
                <x-button type="submit">Save Changes</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
