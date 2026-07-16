<x-layouts.app title="Activations">
    <x-page-header title="Activations" icon="check-circle" subtitle="Devices that have activated a license." />

    @if ($activations->isEmpty())
        <x-card>
            <x-empty-state icon="check-circle" title="No Activations Yet" description="Activations appear here when a device activates a license key.">
            </x-empty-state>
        </x-card>
    @else
        <x-table>
            <thead>
                <tr><th>License Key</th><th>Product</th><th>Fingerprint</th><th>Host</th><th>IP</th><th>Last Seen</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($activations as $a)
                    <tr>
                        <td class="font-mono text-xs"><a href="{{ route('licenses.show', $a->license) }}" class="text-brand-700 hover:underline">{{ optional($a->license)->key }}</a></td>
                        <td class="text-slate-600">{{ optional(optional($a->license)->product)->name }}</td>
                        <td class="font-mono text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($a->fingerprint, 24) }}</td>
                        <td class="text-slate-500">{{ $a->hostname ?: '—' }}</td>
                        <td class="font-mono text-xs text-slate-500">{{ $a->ip ?: '—' }}</td>
                        <td class="text-slate-500">{{ optional($a->last_seen_at)->diffForHumans() ?? '—' }}</td>
                        <td class="text-right">
                            <x-delete-button :name="'del-act-' . $a->id" :action="route('activations.destroy', $a)"
                                title="Release Activation?" message="The device can re-activate on next check." confirm="Release" label="Release" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        <div class="mt-4">{{ $activations->links() }}</div>
    @endif
</x-layouts.app>
