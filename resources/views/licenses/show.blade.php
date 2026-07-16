<x-layouts.app :title="'License ' . $license->key">
    <x-page-header :title="$license->key" icon="license-key"
        :subtitle="optional($license->product)->name . (optional($license->plan)->name ? ' · ' . $license->plan->name : '')"
        :back="['href' => route('licenses.index'), 'label' => 'Licenses']">
        <x-slot:actions>
            <x-button variant="secondary" icon="arrow-up" href="{{ route('licenses.download', $license) }}">Download .license</x-button>
            <x-button variant="secondary" icon="edit" href="{{ route('licenses.edit', $license) }}">Edit</x-button>
        </x-slot:actions>
    </x-page-header>

    @php $ec = ['active' => 'success', 'suspended' => 'warn', 'revoked' => 'danger', 'expired' => 'neutral'][$license->effectiveStatus()] ?? 'neutral'; @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Lifecycle --}}
            <x-card>
                <div class="flex flex-wrap items-center gap-3">
                    <x-badge :color="$ec">{{ $license->statusLabel() }}</x-badge>
                    <span class="text-sm text-slate-400">|</span>
                    <form method="POST" action="{{ route('licenses.setstatus', $license) }}" class="flex items-center gap-2">
                        @csrf
                        <select name="status" class="rounded-lg border-0 bg-white px-3 py-1.5 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-500">
                            @foreach (\App\Models\License::STATUSES as $sc => $sl)
                                <option value="{{ $sc }}" @selected($license->status === $sc)>{{ $sl }}</option>
                            @endforeach
                        </select>
                        <x-button type="submit" variant="secondary" size="sm">Set Status</x-button>
                    </form>
                    <form method="POST" action="{{ route('licenses.renew', $license) }}" class="flex items-center gap-2">
                        @csrf
                        <select name="months" class="rounded-lg border-0 bg-white px-3 py-1.5 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-500">
                            <option value="12">+1 year</option><option value="24">+2 years</option><option value="6">+6 months</option>
                        </select>
                        <x-button type="submit" variant="secondary" size="sm" icon="refresh">Renew</x-button>
                    </form>
                    <span class="flex-1"></span>
                    <x-delete-button :name="'del-lic'" :action="route('licenses.destroy', $license)"
                        title="Delete License?" message="It moves to the Deleted view and stops replicating to nodes." />
                </div>
            </x-card>

            {{-- Details --}}
            <x-card title="Details">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="text-slate-500">Key</dt><dd class="font-mono text-slate-900 break-all">{{ $license->key }}</dd></div>
                    <div><dt class="text-slate-500">Product</dt><dd class="text-slate-900">{{ optional($license->product)->name }}</dd></div>
                    <div><dt class="text-slate-500">Plan</dt><dd class="text-slate-900">{{ optional($license->plan)->name ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Seats</dt><dd class="text-slate-900">{{ $license->activations->count() }} / {{ $license->max_activations ?: 'Unlimited' }}</dd></div>
                    <div><dt class="text-slate-500">Expires</dt><dd class="text-slate-900">{{ optional($license->expires_at)->format('M j, Y') ?? 'Never' }}</dd></div>
                    <div><dt class="text-slate-500">Customer</dt><dd class="text-slate-900">@if($license->customer)<a href="{{ route('customers.show', $license->customer) }}" class="text-brand-700 hover:underline">{{ $license->customer->name }}</a>@else{{ $license->customer_name ?: '—' }}@endif</dd></div>
                    <div><dt class="text-slate-500">Email</dt><dd class="text-slate-900">{{ optional($license->customer)->email ?: ($license->customer_email ?: '—') }}</dd></div>
                    <div><dt class="text-slate-500">Signed</dt><dd class="text-slate-900">{{ optional($license->signed_at)->format('M j, Y g:ia') ?? '—' }}</dd></div>
                </dl>
                @if ($license->notes)
                    <div class="mt-4 pt-4 border-t border-slate-100 text-sm text-slate-600">{{ $license->notes }}</div>
                @endif
            </x-card>

            {{-- Activations --}}
            <x-card title="Activations ({{ $license->activations->count() }})">
                @if ($license->activations->isEmpty())
                    <p class="text-sm text-slate-500">No activations recorded.</p>
                @else
                    <x-table>
                        <thead><tr><th>Fingerprint</th><th>Host</th><th>IP</th><th>Last Seen</th></tr></thead>
                        <tbody>
                            @foreach ($license->activations as $a)
                                <tr>
                                    <td class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($a->fingerprint, 24) }}</td>
                                    <td class="text-slate-500">{{ $a->hostname ?: '—' }}</td>
                                    <td class="text-slate-500 font-mono text-xs">{{ $a->ip ?: '—' }}</td>
                                    <td class="text-slate-500">{{ optional($a->last_seen_at)->diffForHumans() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>

        {{-- Entitlements --}}
        <div class="space-y-6">
            <x-card title="Entitlements">
                @if (empty($license->entitlements))
                    <p class="text-sm text-slate-500">No feature entitlements on this license.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($license->entitlements as $code)
                            <x-badge color="info">{{ $code }}</x-badge>
                        @endforeach
                    </div>
                @endif
            </x-card>
            <x-card title="Replication">
                <p class="text-sm text-slate-600">
                    @if ($license->isValid())
                        <span class="inline-flex items-center gap-1.5 text-emerald-700"><x-icon name="check-circle" class="w-4 h-4" /> Replicating to all active license servers.</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-slate-500"><x-icon name="x-circle" class="w-4 h-4" /> Not replicated (only valid licenses sync to nodes).</span>
                    @endif
                </p>
            </x-card>
        </div>
    </div>
</x-layouts.app>
