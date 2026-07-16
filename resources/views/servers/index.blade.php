<x-layouts.app title="License Servers">
    <x-page-header title="License Servers" icon="server" subtitle="Verification nodes. Each active node replicates every valid license and validates locally.">
        <x-slot:actions>
            @if ($canAdd)
                <x-button icon="plus" href="{{ route('servers.create') }}">Add Server</x-button>
            @else
                <x-button variant="secondary" icon="lock" href="{{ route('settings.license.edit') }}">Add Server</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-stat label="Servers" :value="$servers->count()" icon="server" />
        <x-stat label="Active" :value="$servers->where('status', 'active')->count()" icon="check-circle" />
        <x-stat label="Replicating Licenses" :value="$replicable" icon="license-key" />
    </div>

    {{-- Entitlement --}}
    <x-card class="mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-brand-50 text-brand-600 ring-1 ring-brand-100"><x-icon name="license-key" class="w-5 h-5" /></span>
                <div>
                    <p class="text-sm font-medium text-slate-900">Additional License Servers</p>
                    <p class="text-xs text-slate-500">The main panel is included free. Extra nodes use your license entitlement.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-slate-600">Used <span class="font-semibold text-slate-900 tabular">{{ $used }}</span> / {{ $limit }}</span>
                @if ($canAdd)<x-badge color="success">{{ $limit - $used }} available</x-badge>
                @else<x-badge color="warn">At limit</x-badge>@endif
            </div>
        </div>
        @unless ($canAdd)
            <p class="mt-3 pt-3 border-t border-slate-100 text-sm text-slate-600">
                To run more verification nodes, add license entitlement on the
                <a href="{{ route('settings.license.edit') }}" class="text-brand-700 font-medium hover:underline">Instance License</a> page.
            </p>
        @endunless
    </x-card>

    <x-table>
        <thead>
            <tr><th>Name</th><th>Location</th>@if (auth()->user()->isAdmin())<th>Owner</th>@endif<th>Status</th><th>Online</th><th>Licenses</th><th>Last Sync</th><th class="text-right">Actions</th></tr>
        </thead>
        <tbody>
            @foreach ($servers as $s)
                <tr>
                    <td class="font-medium text-slate-900">
                        <a href="{{ route('servers.show', $s) }}" class="hover:text-brand-700">{{ $s->name }}</a>
                        @if ($s->is_local)<x-badge color="info" class="ml-1.5">Built-in</x-badge>@endif
                        @if ($s->hostname)<div class="text-xs text-slate-400 font-mono">{{ $s->hostname }}</div>@endif
                    </td>
                    <td class="text-slate-500">{{ optional($s->location)->name ?? '—' }}</td>
                    @if (auth()->user()->isAdmin())<td class="text-slate-500">{{ $s->is_local ? '—' : ($s->owner?->name ?? 'Unassigned') }}</td>@endif
                    <td><x-badge :color="['active' => 'success', 'pending' => 'warn', 'disabled' => 'neutral'][$s->status] ?? 'neutral'">{{ $s->statusLabel() }}</x-badge></td>
                    <td>@if ($s->is_local)<x-badge color="success" dot>This panel</x-badge>@elseif ($s->isOnline())<x-badge color="success" dot>Online</x-badge>@else<span class="text-xs text-slate-400">Offline</span>@endif</td>
                    <td class="tabular text-slate-500">{{ $s->is_local ? $replicable : $s->license_count }}</td>
                    <td class="text-slate-500">{{ $s->is_local ? 'Live' : (optional($s->last_sync_at)->diffForHumans() ?? 'Never') }}</td>
                    <td class="text-right">
                        <div class="inline-flex items-center gap-2">
                            <x-icon-button :href="route('servers.show', $s)" icon="eye" title="Open" />
                            @unless ($s->is_local)
                                <x-icon-button :href="route('servers.edit', $s)" icon="edit" title="Edit" />
                                <x-delete-button :name="'del-srv-' . $s->id" :action="route('servers.destroy', $s)"
                                    title="Remove License Server?" message="The node stops receiving license updates. Uninstall it separately." />
                            @endunless
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-table>
</x-layouts.app>
