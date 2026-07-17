<x-layouts.app title="Activations">
    <x-page-header title="Activations" icon="check-circle" subtitle="Devices that have activated a license." />

    @if ($activations->isEmpty())
        <x-card>
            <x-empty-state icon="check-circle" title="No Activations Yet" description="Activations appear here when a device activates a license key.">
            </x-empty-state>
        </x-card>
    @else
        <div
            x-data="{
                selected: [],
                confirming: false,
                allIds: [{{ $activations->pluck('id')->implode(',') }}],
                toggleAll(e) { this.selected = e.target.checked ? [...this.allIds] : []; this.confirming = false; },
                submitBulk() {
                    const f = this.$refs.bulkForm;
                    f.querySelectorAll('input.js-dyn').forEach(n => n.remove());
                    this.selected.forEach(id => {
                        const i = document.createElement('input');
                        i.type = 'hidden'; i.name = 'ids[]'; i.value = id; i.className = 'js-dyn';
                        f.appendChild(i);
                    });
                    f.submit();
                }
            }">
            {{-- Hidden form the bulk delete posts through. --}}
            <form method="POST" action="{{ route('activations.bulk-destroy') }}" x-ref="bulkForm" class="hidden">@csrf @method('DELETE')</form>

            {{-- Bulk actions bar: appears once at least one activation is selected. --}}
            <div x-show="selected.length" x-cloak class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-brand-50 px-4 py-2.5 ring-1 ring-inset ring-brand-200">
                <span class="text-sm font-medium text-brand-800"><span x-text="selected.length"></span> selected</span>
                <div class="flex items-center gap-2">
                    <template x-if="! confirming">
                        <x-button type="button" variant="danger" size="sm" icon="trash" x-on:click="confirming = true">Release Selected</x-button>
                    </template>
                    <template x-if="confirming">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm text-brand-800">Release <span x-text="selected.length"></span> activation(s)?</span>
                            <x-button type="button" variant="secondary" size="sm" x-on:click="confirming = false">Cancel</x-button>
                            <x-button type="button" variant="danger" size="sm" icon="trash" x-on:click="submitBulk()">Confirm Release</x-button>
                        </div>
                    </template>
                </div>
            </div>

            <x-table>
                <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" x-on:change="toggleAll($event)"
                                :checked="selected.length > 0 && selected.length === allIds.length"
                                :disabled="allIds.length === 0"
                                class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 align-middle" aria-label="Select all activations">
                        </th>
                        <th>License Key</th><th>Product</th><th>Fingerprint</th><th>Host</th><th>IP</th><th>Last Seen</th><th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($activations as $a)
                        <tr>
                            <td>
                                <input type="checkbox" x-model.number="selected" value="{{ $a->id }}" x-on:change="confirming = false"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 align-middle" aria-label="Select activation">
                            </td>
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
        </div>
        <div class="mt-4">{{ $activations->links() }}</div>
    @endif
</x-layouts.app>
