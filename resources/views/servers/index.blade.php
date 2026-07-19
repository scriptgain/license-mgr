<x-layouts.app title="License Servers">
    <x-page-header title="License Servers" icon="server" subtitle="Verification nodes. Each active node replicates every valid license and validates locally.">
        <x-slot:actions>
            <x-button icon="plus" href="{{ route('servers.create') }}">Add Server</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-stat label="Servers" :value="$servers->count()" icon="server" />
        <x-stat label="Active" :value="$servers->where('status', 'active')->count()" icon="check-circle" />
        <x-stat label="Replicating Licenses" :value="$replicable" icon="license-key" />
    </div>

    @php
        $isAdmin = auth()->user()->isAdmin();
        $ownerUsers = $servers->reject->is_local->map->owner->filter(fn ($u) => $u && ! $u->isAdmin())->unique('id')->sortBy('name')->values();
        $hasAdminOwned = $servers->reject->is_local->contains(fn ($s) => $s->owner && $s->owner->isAdmin());
        $locations = $servers->map->location->filter()->unique('id')->sortBy('name')->values();
        $showOwnerFilter = $isAdmin && ($ownerUsers->count() || $hasAdminOwned);
    @endphp

    <div x-data="{ fOwner: 'all', fLoc: 'all' }">
        @if ($showOwnerFilter || $locations->count())
            <div class="mb-4 flex flex-wrap items-center gap-4">
                @if ($showOwnerFilter)
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium uppercase tracking-wide text-slate-400">Owner</span>
                        <div class="w-44">
                            <x-select x-model="fOwner">
                                <option value="all">All Owners</option>
                                @if ($hasAdminOwned)<option value="admin">Admin Only</option>@endif
                                @foreach ($ownerUsers as $u)<option value="user-{{ $u->id }}">{{ $u->name }}</option>@endforeach
                            </x-select>
                        </div>
                    </div>
                @endif
                @if ($locations->count())
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium uppercase tracking-wide text-slate-400">Location</span>
                        <div class="w-44">
                            <x-select x-model="fLoc">
                                <option value="all">All Locations</option>
                                @foreach ($locations as $loc)<option value="loc-{{ $loc->id }}">{{ $loc->name }}</option>@endforeach
                            </x-select>
                        </div>
                    </div>
                @endif
                <button type="button" x-show="fOwner !== 'all' || fLoc !== 'all'" x-cloak @click="fOwner='all'; fLoc='all'" class="text-xs font-medium text-slate-500 hover:text-brand-700">Clear Filters</button>
            </div>
        @endif

        <x-table>
            <thead>
                <tr><th style="width: 16rem">Name</th><th>Location</th><th>Status</th><th>Online</th><th>Licenses</th><th>Last Sync</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($servers as $s)
                    @php
                        $ownerKey = $s->is_local ? null : ($s->owner ? ($s->owner->isAdmin() ? 'admin' : 'user-' . $s->owner->id) : 'unassigned');
                        $locKey = $s->location_id ? 'loc-' . $s->location_id : 'none';
                    @endphp
                    <tr @unless ($s->is_local) x-show="(fOwner==='all' || fOwner==='{{ $ownerKey }}') && (fLoc==='all' || fLoc==='{{ $locKey }}')" @endunless>
                        <td>
                            <div class="flex items-center gap-2 min-w-0">
                                <a href="{{ route('servers.show', $s) }}" class="truncate text-[0.95rem] font-semibold text-slate-900 hover:text-brand-700">{{ $s->name }}</a>
                                @if ($s->is_local)<x-badge color="info" class="shrink-0">Built-in</x-badge>@endif
                            </div>
                            @if ($s->hostname)<div class="mt-0.5 truncate font-mono text-xs text-slate-400">{{ $s->hostname }}</div>@endif
                        </td>
                        <td class="text-slate-500">{{ optional($s->location)->name ?? '—' }}</td>
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
    </div>
</x-layouts.app>
