<x-layouts.app title="Customers">
    <x-page-header title="Customers" icon="users" subtitle="People and companies that hold licenses.">
        <x-slot:actions>
            <x-button icon="plus" href="{{ route('customers.create') }}">New Customer</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($customers->isEmpty())
        <x-card>
            <x-empty-state icon="users" title="No Customers Yet" description="Add a customer to associate with issued licenses.">
                <x-slot:action><x-button icon="plus" href="{{ route('customers.create') }}">New Customer</x-button></x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <x-table>
            <thead>
                <tr><th>Name</th><th>Email</th><th>Company</th>@if (auth()->user()->isAdmin())<th>Owner</th>@endif<th>Licenses</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($customers as $c)
                    <tr>
                        <td class="font-medium text-slate-900"><a href="{{ route('customers.show', $c) }}" class="hover:text-brand-700">{{ $c->name }}</a></td>
                        <td class="text-slate-500">{{ $c->email ?: '—' }}</td>
                        <td class="text-slate-500">{{ $c->company ?: '—' }}</td>
                        @if (auth()->user()->isAdmin())<td class="text-slate-500">{{ $c->owner?->name ?? 'Unassigned' }}</td>@endif
                        <td class="tabular">{{ $c->licenses_count }}</td>
                        <td class="text-right">
                            <div class="inline-flex items-center gap-2">
                                <x-icon-button :href="route('customers.show', $c)" icon="eye" title="Open" />
                                <x-icon-button :href="route('customers.edit', $c)" icon="edit" title="Edit" />
                                <x-delete-button :name="'del-cust-' . $c->id" :action="route('customers.destroy', $c)"
                                    title="Delete Customer?" message="Licenses tied to this customer will be unassigned (not deleted)." />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    @endif
</x-layouts.app>
