<x-layouts.app title="Add License Server">
    <x-page-header title="Add License Server" icon="server" subtitle="Register a verification node. You'll get a one-line installer next."
        :back="['href' => route('servers.index'), 'label' => 'License Servers']" />

    <x-card>
        <form method="POST" action="{{ route('servers.store') }}" class="space-y-5">
            @csrf
            @include('servers._fields', ['server' => null])
            <div class="flex items-center justify-end gap-2 pt-1">
                <x-button variant="secondary" href="{{ route('servers.index') }}">Cancel</x-button>
                <x-button type="submit" icon="plus">Register Server</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
