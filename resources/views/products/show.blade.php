<x-layouts.app :title="$product->name">
    <x-page-header :title="$product->name" icon="archive" :subtitle="$product->description"
        :back="['href' => route('products.index'), 'label' => 'Products']">
        <x-slot:actions>
            <x-button variant="secondary" icon="edit" href="{{ route('products.edit', $product) }}">Edit</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Features --}}
            <x-card title="Features" subtitle="Entitlement flags this product can grant." :flush="$product->features->isNotEmpty()">
                @if ($product->features->isEmpty())
                    <p class="text-sm text-slate-500">No features yet. Add one below.</p>
                @else
                    <x-table flush>
                        <thead><tr><th>Name</th><th>Code</th><th>Description</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                            @foreach ($product->features as $feature)
                                <tr>
                                    <td class="font-medium text-slate-900">{{ $feature->name }}</td>
                                    <td class="font-mono text-xs text-slate-500">{{ $feature->code }}</td>
                                    <td class="text-slate-500">{{ $feature->description ?: '—' }}</td>
                                    <td class="text-right">
                                        <x-delete-button :name="'del-feat-' . $feature->id" :action="route('features.destroy', [$product, $feature])"
                                            title="Delete Feature?" message="Plans and licenses referencing this feature may be affected." />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
                <x-slot:footer>
                    <form method="POST" action="{{ route('features.store', $product) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <x-field label="Name" for="feature_name" required class="flex-1 min-w-[8rem]" :error="$errors->first('name')">
                            <x-input id="feature_name" name="name" :value="old('name')" required placeholder="e.g. Cloud Sync" />
                        </x-field>
                        <x-field label="Code" for="feature_code" hint="auto from name if blank" class="flex-1 min-w-[8rem]" :error="$errors->first('code')">
                            <x-input id="feature_code" name="code" :value="old('code')" placeholder="e.g. cloud_sync" />
                        </x-field>
                        <x-field label="Description" for="feature_description" class="flex-1 min-w-[10rem]" :error="$errors->first('description')">
                            <x-input id="feature_description" name="description" :value="old('description')" />
                        </x-field>
                        <x-button type="submit" icon="plus">Add Feature</x-button>
                    </form>
                </x-slot:footer>
            </x-card>

            {{-- Plans --}}
            <x-card title="Plans" subtitle="Pricing and entitlement tiers for this product." :flush="$product->plans->isNotEmpty()">
                <x-slot:actions>
                    <x-button variant="secondary" size="sm" icon="plus" href="{{ route('plans.create', ['product' => $product->id]) }}">New Plan</x-button>
                </x-slot:actions>
                @if ($product->plans->isEmpty())
                    <p class="text-sm text-slate-500">No plans yet.</p>
                @else
                    <x-table flush>
                        <thead><tr><th>Name</th><th>Price</th><th>Interval</th></tr></thead>
                        <tbody>
                            @foreach ($product->plans as $plan)
                                <tr>
                                    <td class="font-medium text-slate-900"><a href="{{ route('plans.show', $plan) }}" class="hover:text-brand-700">{{ $plan->name }}</a></td>
                                    <td class="tabular text-slate-700">${{ number_format($plan->price, 2) }}</td>
                                    <td class="text-slate-500">{{ \App\Models\Plan::INTERVALS[$plan->interval] ?? $plan->interval }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>

        {{-- Details --}}
        <div class="space-y-6">
            <x-card title="Details">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-slate-500">Key Prefix</dt><dd class="font-mono text-slate-900">{{ $product->key_prefix ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Default Seats</dt><dd class="text-slate-900">{{ $product->default_max_activations ?: 'Unlimited' }}</dd></div>
                    <div><dt class="text-slate-500">Default Expiry</dt><dd class="text-slate-900">{{ $product->default_expiry_days ? $product->default_expiry_days . ' days' : 'Perpetual' }}</dd></div>
                </dl>
            </x-card>
        </div>
    </div>
</x-layouts.app>
