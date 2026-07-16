<x-layouts.app title="Licenses">
    <x-page-header title="Licenses" icon="license-key" subtitle="Issue and manage license keys. Valid keys replicate to every license server.">
        <x-slot:actions>
            <x-button icon="plus" href="{{ route('licenses.create') }}">Issue License</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-stat label="Total" :value="$stats['total']" icon="license-key" />
        <x-stat label="Active" :value="$stats['active']" icon="check-circle" />
        <x-stat label="Revoked" :value="$stats['revoked']" icon="x-circle" />
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-4 text-sm">
        <a href="{{ route('licenses.index') }}" @class(['px-3 py-1.5 rounded-lg font-medium', 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200' => ! $status, 'text-slate-600 hover:bg-slate-100' => $status])>All</a>
        @foreach (\App\Models\License::STATUSES as $sc => $sl)
            <a href="{{ route('licenses.index', ['status' => $sc]) }}" @class(['px-3 py-1.5 rounded-lg font-medium', 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200' => $status === $sc, 'text-slate-600 hover:bg-slate-100' => $status !== $sc])>{{ $sl }}</a>
        @endforeach
        <a href="{{ route('licenses.index', ['status' => 'deleted']) }}" @class(['px-3 py-1.5 rounded-lg font-medium', 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200' => $status === 'deleted', 'text-slate-600 hover:bg-slate-100' => $status !== 'deleted'])>Deleted</a>
    </div>

    @if ($licenses->isEmpty())
        <x-card>
            <x-empty-state icon="license-key" title="No Licenses Here" description="Issue a license against one of your products.">
                <x-slot:action><x-button icon="plus" href="{{ route('licenses.create') }}">Issue License</x-button></x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <x-table>
            <thead>
                <tr><th>Key</th><th>Product</th><th>Plan</th><th>Status</th><th>Seats</th><th>Expires</th><th>Customer</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($licenses as $l)
                    <tr>
                        <td class="font-mono text-xs"><a href="{{ route('licenses.show', $l) }}" class="text-brand-700 hover:underline">{{ $l->key }}</a></td>
                        <td class="text-slate-600">{{ optional($l->product)->name }}</td>
                        <td class="text-slate-500">{{ optional($l->plan)->name ?? '—' }}</td>
                        <td>
                            @if ($l->trashed())<x-badge color="neutral">Deleted</x-badge>
                            @else <x-badge :color="['active' => 'success', 'suspended' => 'warn', 'revoked' => 'danger', 'expired' => 'neutral'][$l->effectiveStatus()] ?? 'neutral'">{{ $l->statusLabel() }}</x-badge>@endif
                        </td>
                        <td class="tabular text-slate-500">{{ $l->activations_count }} / {{ $l->max_activations ?: '∞' }}</td>
                        <td class="text-slate-500">{{ optional($l->expires_at)->format('M j, Y') ?? 'Never' }}</td>
                        <td class="text-slate-500">{{ $l->customer_email ?: (optional($l->customer)->name ?: '—') }}</td>
                        <td class="text-right"><x-icon-button :href="route('licenses.show', $l)" icon="eye" title="Open" /></td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        <div class="mt-4">{{ $licenses->links() }}</div>
    @endif
</x-layouts.app>
