<x-layouts.app :title="$plan->name">
    <x-page-header :title="$plan->name" icon="archive"
        :subtitle="optional($plan->product)->name"
        :back="['href' => route('plans.index'), 'label' => 'Plans']">
        <x-slot:actions>
            <x-button variant="secondary" icon="edit" href="{{ route('plans.edit', $plan) }}">Edit</x-button>
            <x-delete-button :name="'del-plan-' . $plan->id" :action="route('plans.destroy', $plan)"
                title="Delete Plan?" message="Licenses on this plan may be affected." />
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Details">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="text-slate-500">Product</dt><dd class="text-slate-900"><a href="{{ route('products.show', $plan->product) }}" class="text-brand-700 hover:underline">{{ optional($plan->product)->name }}</a></dd></div>
                    <div><dt class="text-slate-500">Price</dt><dd class="text-slate-900 tabular">${{ number_format($plan->price, 2) }}</dd></div>
                    <div><dt class="text-slate-500">Interval</dt><dd class="text-slate-900">{{ \App\Models\Plan::INTERVALS[$plan->interval] ?? $plan->interval }}</dd></div>
                    <div><dt class="text-slate-500">Max Activations</dt><dd class="text-slate-900">{{ $plan->max_activations ?: 'Unlimited' }}</dd></div>
                    <div><dt class="text-slate-500">Expiry</dt><dd class="text-slate-900">{{ $plan->expiry_days ? $plan->expiry_days . ' days' : 'Perpetual' }}</dd></div>
                </dl>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Features">
                @if ($plan->features->isEmpty())
                    <p class="text-sm text-slate-500">No features.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($plan->features as $feature)
                            <x-badge color="info">{{ $feature->name }}</x-badge>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
