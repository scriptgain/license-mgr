<x-layouts.app title="Products">
    <x-page-header title="Products" icon="archive" subtitle="The software products you license. Each product has features, plans, and licenses.">
        <x-slot:actions>
            <x-button icon="plus" href="{{ route('products.create') }}">New Product</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($products->isEmpty())
        <x-card>
            <x-empty-state icon="archive" title="No Products Yet" description="Create a product, then define its plans and features.">
                <x-slot:action><x-button icon="plus" href="{{ route('products.create') }}">New Product</x-button></x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <x-table>
            <thead>
                <tr><th>Name</th><th>Prefix</th><th>Plans</th><th>Licenses</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($products as $p)
                    <tr>
                        <td class="font-medium text-slate-900"><a href="{{ route('products.show', $p) }}" class="hover:text-brand-700">{{ $p->name }}</a></td>
                        <td class="font-mono text-xs text-slate-500">{{ $p->key_prefix ?: '—' }}</td>
                        <td class="tabular">{{ $p->plans_count }}</td>
                        <td class="tabular">{{ $p->licenses_count }}</td>
                        <td class="text-right">
                            <div class="inline-flex items-center gap-2">
                                <x-icon-button :href="route('products.show', $p)" icon="eye" title="Open" />
                                <x-icon-button :href="route('products.edit', $p)" icon="edit" title="Edit" />
                                <x-delete-button :name="'del-prod-' . $p->id" :action="route('products.destroy', $p)"
                                    title="Delete Product?" message="Plans, features, and licenses tied to this product may be affected." />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    @endif
</x-layouts.app>
