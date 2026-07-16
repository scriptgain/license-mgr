<x-layouts.app title="Plans">
    <x-page-header title="Plans" icon="archive" subtitle="Pricing and entitlement tiers across your products.">
        <x-slot:actions>
            <x-button icon="plus" href="{{ route('plans.create') }}">New Plan</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($plans->isEmpty())
        <x-card>
            <x-empty-state icon="archive" title="No Plans Yet" description="Create a plan under one of your products.">
                <x-slot:action><x-button icon="plus" href="{{ route('plans.create') }}">New Plan</x-button></x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <x-table>
            <thead>
                <tr><th>Name</th><th>Product</th><th>Price</th><th>Interval</th><th>Seats</th><th>Licenses</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($plans as $p)
                    <tr>
                        <td class="font-medium text-slate-900"><a href="{{ route('plans.show', $p) }}" class="hover:text-brand-700">{{ $p->name }}</a></td>
                        <td class="text-slate-600">{{ optional($p->product)->name }}</td>
                        <td class="tabular text-slate-700">${{ number_format($p->price, 2) }}</td>
                        <td class="text-slate-500">{{ \App\Models\Plan::INTERVALS[$p->interval] ?? $p->interval }}</td>
                        <td class="tabular text-slate-500">{{ $p->max_activations ?: '∞' }}</td>
                        <td class="tabular">{{ $p->licenses_count }}</td>
                        <td class="text-right">
                            <div class="inline-flex items-center gap-2">
                                <x-icon-button :href="route('plans.show', $p)" icon="eye" title="Open" />
                                <x-icon-button :href="route('plans.edit', $p)" icon="edit" title="Edit" />
                                <x-delete-button :name="'del-plan-' . $p->id" :action="route('plans.destroy', $p)"
                                    title="Delete Plan?" message="Licenses on this plan may be affected." />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    @endif
</x-layouts.app>
