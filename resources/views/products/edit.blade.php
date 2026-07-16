<x-layouts.app title="Edit Product">
    <x-page-header title="Edit Product" icon="archive" :subtitle="$product->name"
        :back="['href' => route('products.show', $product), 'label' => $product->name]" />

    <x-card>
        <form method="POST" action="{{ route('products.update', $product) }}" class="space-y-5">
            @csrf
            @method('PUT')
            <x-field label="Name" for="name" required :error="$errors->first('name')">
                <x-input id="name" name="name" :value="old('name', $product->name)" required autofocus />
            </x-field>
            <x-field label="Description" for="description" :error="$errors->first('description')">
                <textarea id="description" name="description" rows="3"
                    class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('description', $product->description) }}</textarea>
            </x-field>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-field label="Key Prefix" for="key_prefix" hint="Letters/numbers, e.g. ACME" :error="$errors->first('key_prefix')">
                    <x-input id="key_prefix" name="key_prefix" :value="old('key_prefix', $product->key_prefix)" placeholder="e.g. ACME" />
                </x-field>
                <x-field label="Default Max Activations" for="default_max_activations" required hint="Seats per license by default." :error="$errors->first('default_max_activations')">
                    <x-input id="default_max_activations" name="default_max_activations" type="number" min="0" :value="old('default_max_activations', $product->default_max_activations)" required />
                </x-field>
            </div>
            <x-field label="Default Expiry Days" for="default_expiry_days" hint="Blank = perpetual" :error="$errors->first('default_expiry_days')">
                <x-input id="default_expiry_days" name="default_expiry_days" type="number" min="0" :value="old('default_expiry_days', $product->default_expiry_days)" placeholder="e.g. 365" />
            </x-field>
            <div class="flex items-center justify-end gap-2 pt-1">
                <x-button variant="secondary" href="{{ route('products.show', $product) }}">Cancel</x-button>
                <x-button type="submit" icon="check">Save Changes</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
