<x-layouts.app title="Issue License">
    <x-page-header title="Issue License" icon="license-key" subtitle="Generate a signed license key for a product."
        :back="['href' => route('licenses.index'), 'label' => 'Licenses']" />

    @if ($products->isEmpty())
        <x-card>
            <x-empty-state icon="archive" title="No Products Yet" description="Create a product first — licenses belong to a product.">
                <x-slot:action><x-button icon="plus" href="{{ route('products.create') }}">New Product</x-button></x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <x-card>
            <form method="POST" action="{{ route('licenses.store') }}" class="space-y-5">
                @csrf
                <x-field label="Product" for="product_id" required :error="$errors->first('product_id')">
                    <select id="product_id" name="product_id" required
                        class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                @include('licenses._fields', ['license' => null])
                <div class="flex items-center justify-end gap-2 pt-1">
                    <x-button variant="secondary" href="{{ route('licenses.index') }}">Cancel</x-button>
                    <x-button type="submit" icon="plus">Issue License</x-button>
                </div>
            </form>
        </x-card>
    @endif
</x-layouts.app>
