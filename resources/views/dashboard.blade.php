<x-layouts.app title="Dashboard">
    <x-page-header title="Dashboard" subtitle="License operations at a glance.">
        <x-slot:actions>
            <x-button variant="secondary" size="sm" icon="server" href="{{ route('servers.index') }}">Servers</x-button>
            <x-button size="sm" icon="plus" href="{{ route('licenses.create') }}">Issue License</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat label="Total Licenses" :value="number_format($stats['licenses'])" icon="license-key" />
        <x-stat label="Active" :value="number_format($stats['active'])" icon="check-circle" />
        <x-stat label="License Servers" :value="$stats['servers']" icon="server" />
        <x-stat label="Customers" :value="number_format($stats['customers'])" icon="users" />
    </div>

    {{-- Health strip --}}
    <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Expiring (30 days)</p>
                    <p class="mt-1 text-2xl font-semibold tabular {{ $expiringSoon ? 'text-amber-600' : 'text-slate-900' }}">{{ $expiringSoon }}</p>
                </div>
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg ring-1 {{ $expiringSoon ? 'bg-amber-50 text-amber-600 ring-amber-100' : 'bg-slate-50 text-slate-400 ring-slate-100' }}"><x-icon name="clock" class="w-5 h-5" /></span>
            </div>
        </x-card>
        <x-card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Revoked</p>
                    <p class="mt-1 text-2xl font-semibold tabular {{ $revoked ? 'text-rose-600' : 'text-slate-900' }}">{{ $revoked }}</p>
                </div>
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg ring-1 {{ $revoked ? 'bg-rose-50 text-rose-600 ring-rose-100' : 'bg-slate-50 text-slate-400 ring-slate-100' }}"><x-icon name="x-circle" class="w-5 h-5" /></span>
            </div>
        </x-card>
        <x-card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Activations (24h)</p>
                    <p class="mt-1 text-2xl font-semibold tabular text-slate-900">{{ number_format($activations24h) }}</p>
                </div>
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg ring-1 bg-brand-50 text-brand-600 ring-brand-100"><x-icon name="check-circle" class="w-5 h-5" /></span>
            </div>
        </x-card>
    </div>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Recent licenses --}}
        <div class="lg:col-span-2">
            <x-card title="Recent Licenses">
                @if ($recent->isEmpty())
                    <x-empty-state icon="license-key" title="No Licenses Yet" description="Issue your first license key.">
                        <x-slot:action><x-button icon="plus" href="{{ route('licenses.create') }}">Issue License</x-button></x-slot:action>
                    </x-empty-state>
                @else
                    <x-table>
                        <thead><tr><th>Key</th><th>Product</th><th>Status</th><th>Customer</th></tr></thead>
                        <tbody>
                            @foreach ($recent as $l)
                                <tr>
                                    <td class="font-mono text-xs"><a href="{{ route('licenses.show', $l) }}" class="text-brand-700 hover:underline">{{ $l->key }}</a></td>
                                    <td class="text-slate-600">{{ optional($l->product)->name }}</td>
                                    <td><x-badge :color="['active' => 'success', 'suspended' => 'warn', 'revoked' => 'danger', 'expired' => 'neutral'][$l->effectiveStatus()] ?? 'neutral'">{{ $l->statusLabel() }}</x-badge></td>
                                    <td class="text-slate-500">{{ $l->customer_email ?: (optional($l->customer)->name ?: '—') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>

        {{-- Servers --}}
        <div>
            <x-card title="License Servers">
                @if ($servers->isEmpty())
                    <x-empty-state icon="server" title="No Servers" description="Add a verification node.">
                        <x-slot:action><x-button size="sm" icon="plus" href="{{ route('servers.create') }}">Add Server</x-button></x-slot:action>
                    </x-empty-state>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($servers as $s)
                            <li class="py-2.5 flex items-center justify-between gap-2">
                                <a href="{{ route('servers.show', $s) }}" class="min-w-0">
                                    <p class="text-sm font-medium text-slate-900 truncate hover:text-brand-700">{{ $s->name }}</p>
                                    <p class="text-xs text-slate-400">{{ optional($s->last_sync_at)->diffForHumans() ?? 'Never synced' }} · {{ $s->license_count }} licenses</p>
                                </a>
                                @if ($s->isOnline())<x-badge color="success" dot>Online</x-badge>
                                @else<x-badge :color="['active' => 'success', 'pending' => 'warn', 'disabled' => 'neutral'][$s->status] ?? 'neutral'">{{ $s->statusLabel() }}</x-badge>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
