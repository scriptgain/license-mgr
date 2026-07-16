<x-layouts.app :title="$customer->name">
    <x-page-header :title="$customer->name" icon="users"
        :subtitle="$customer->company ?: 'Customer'"
        :back="['href' => route('customers.index'), 'label' => 'Customers']">
        <x-slot:actions>
            <x-button variant="secondary" icon="edit" href="{{ route('customers.edit', $customer) }}">Edit</x-button>
            <x-delete-button :name="'del-cust-' . $customer->id" :action="route('customers.destroy', $customer)"
                title="Delete Customer?" message="Licenses tied to this customer will be unassigned (not deleted)." />
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="space-y-6">
            <x-card title="Info">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-slate-500">Email</dt><dd class="text-slate-900">{{ $customer->email ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Company</dt><dd class="text-slate-900">{{ $customer->company ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Phone</dt><dd class="text-slate-900">{{ $customer->phone ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Notes</dt><dd class="text-slate-900 whitespace-pre-line">{{ $customer->notes ?: '—' }}</dd></div>
                </dl>
            </x-card>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <x-card title="Licenses" :flush="$customer->licenses->isNotEmpty()">
                @if ($customer->licenses->isEmpty())
                    <p class="text-sm text-slate-500">No licenses for this customer.</p>
                @else
                    <x-table flush>
                        <thead><tr><th>Key</th><th>Product</th><th>Status</th><th>Expires</th></tr></thead>
                        <tbody>
                            @foreach ($customer->licenses as $l)
                                <tr>
                                    <td class="font-mono text-xs"><a href="{{ route('licenses.show', $l) }}" class="text-brand-700 hover:underline">{{ $l->key }}</a></td>
                                    <td class="text-slate-600">{{ optional($l->product)->name }}</td>
                                    <td><x-badge :color="['active' => 'success', 'suspended' => 'warn', 'revoked' => 'danger', 'expired' => 'neutral'][$l->effectiveStatus()] ?? 'neutral'">{{ $l->statusLabel() }}</x-badge></td>
                                    <td class="text-slate-500">{{ optional($l->expires_at)->format('M j, Y') ?? 'Never' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
