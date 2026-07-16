<x-layouts.app :title="$location->name">
    <x-page-header :title="$location->name" icon="folder"
        :subtitle="collect([$location->address, $location->region])->filter()->implode(' · ') ?: 'Location'">
        <x-slot:actions>
            <x-button variant="secondary" icon="edit" href="{{ route('locations.edit', $location) }}">Edit</x-button>
            <x-button icon="plus" href="{{ route('servers.create') }}">Add Server</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card title="License Servers">
        @if ($location->licenseServers->isEmpty())
            <x-empty-state icon="server" title="No Servers Here" description="Assign a license server to this location.">
                <x-slot:action><x-button icon="plus" href="{{ route('servers.create') }}">Add Server</x-button></x-slot:action>
            </x-empty-state>
        @else
            <x-table>
                <thead><tr><th>Name</th><th>Hostname</th><th>Status</th><th>Online</th><th>Last Sync</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @foreach ($location->licenseServers as $s)
                        <tr>
                            <td class="font-medium text-slate-900"><a href="{{ route('servers.show', $s) }}" class="hover:text-brand-700">{{ $s->name }}</a></td>
                            <td class="text-slate-500 font-mono text-xs">{{ $s->hostname ?: '—' }}</td>
                            <td><x-badge :color="['active' => 'success', 'pending' => 'warn', 'disabled' => 'neutral'][$s->status] ?? 'neutral'">{{ $s->statusLabel() }}</x-badge></td>
                            <td>@if($s->isOnline())<x-badge color="success" dot>Online</x-badge>@else<span class="text-xs text-slate-400">Offline</span>@endif</td>
                            <td class="text-slate-500">{{ optional($s->last_sync_at)->diffForHumans() ?? 'Never' }}</td>
                            <td class="text-right"><x-icon-button :href="route('servers.show', $s)" icon="eye" title="Open" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </x-card>
</x-layouts.app>
