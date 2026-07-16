<x-layouts.app title="Locations">
    <x-page-header title="Locations" icon="folder" subtitle="Sites/regions that group your license servers.">
        <x-slot:actions>
            <x-button icon="plus" href="{{ route('locations.create') }}">New Location</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($locations->isEmpty())
        <x-card>
            <x-empty-state icon="folder" title="No Locations Yet" description="Create a location (site/region), then assign license servers to it.">
                <x-slot:action><x-button icon="plus" href="{{ route('locations.create') }}">New Location</x-button></x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <x-table>
            <thead>
                <tr><th>Name</th><th>Address</th><th>Region</th><th>Servers</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($locations as $l)
                    <tr>
                        <td class="font-medium text-slate-900"><a href="{{ route('locations.show', $l) }}" class="hover:text-brand-700">{{ $l->name }}</a></td>
                        <td class="text-slate-500">{{ $l->address ?? '—' }}</td>
                        <td class="text-slate-500">{{ $l->region ?? '—' }}</td>
                        <td class="tabular">{{ $l->license_servers_count }}</td>
                        <td class="text-right">
                            <div class="inline-flex items-center gap-2">
                                <x-icon-button :href="route('locations.show', $l)" icon="eye" title="Open" />
                                <x-icon-button :href="route('locations.edit', $l)" icon="edit" title="Edit" />
                                <x-delete-button :name="'del-loc-' . $l->id" :action="route('locations.destroy', $l)"
                                    title="Delete Location?" message="License servers in this location will be unassigned (not deleted)." />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    @endif
</x-layouts.app>
