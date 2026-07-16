<x-layouts.app title="Edit Plan">
    <x-page-header title="Edit Plan" icon="archive" :subtitle="$plan->name"
        :back="['href' => route('plans.show', $plan), 'label' => $plan->name]" />

    @php $checkedFeatures = old('features', $plan->features->pluck('id')->all()); @endphp
    <x-card>
        <form method="POST" action="{{ route('plans.update', $plan) }}" class="space-y-5">
            @csrf
            @method('PUT')
            <x-field label="Product" for="product_id" required :error="$errors->first('product_id')">
                <select id="product_id" name="product_id" required
                    class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected(old('product_id', $plan->product_id) == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Name" for="name" required :error="$errors->first('name')">
                <x-input id="name" name="name" :value="old('name', $plan->name)" required />
            </x-field>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-field label="Price" for="price" required :error="$errors->first('price')">
                    <x-input id="price" name="price" type="number" step="0.01" min="0" :value="old('price', $plan->price)" required />
                </x-field>
                <x-field label="Interval" for="interval" required :error="$errors->first('interval')">
                    <select id="interval" name="interval" required
                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                        @foreach (\App\Models\Plan::INTERVALS as $value => $label)
                            <option value="{{ $value }}" @selected(old('interval', $plan->interval) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-field label="Max Activations" for="max_activations" required hint="Seats per license." :error="$errors->first('max_activations')">
                    <x-input id="max_activations" name="max_activations" type="number" min="0" :value="old('max_activations', $plan->max_activations)" required />
                </x-field>
                <x-field label="Expiry Days" for="expiry_days" hint="Blank = perpetual" :error="$errors->first('expiry_days')">
                    <x-input id="expiry_days" name="expiry_days" type="number" min="0" :value="old('expiry_days', $plan->expiry_days)" placeholder="e.g. 365" />
                </x-field>
            </div>

            {{-- Features --}}
            <div class="space-y-4 pt-2 border-t border-slate-100">
                <div>
                    <h3 class="text-sm font-medium text-slate-700">Features</h3>
                    <p class="mt-0.5 text-sm text-slate-500">Tick the features this plan includes.</p>
                </div>
                @foreach ($products as $p)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">{{ $p->name }}</p>
                        @if ($p->features->isEmpty())
                            <p class="text-sm text-slate-400">No features defined.</p>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach ($p->features as $feature)
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox" name="features[]" value="{{ $feature->id }}"
                                            @checked(in_array($feature->id, $checkedFeatures))
                                            class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                        {{ $feature->name }}
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="flex items-center justify-end gap-2 pt-1">
                <x-button variant="secondary" href="{{ route('plans.show', $plan) }}">Cancel</x-button>
                <x-button type="submit" icon="check">Save Changes</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
