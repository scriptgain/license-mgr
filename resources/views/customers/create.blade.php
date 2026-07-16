<x-layouts.app title="New Customer">
    <x-page-header title="New Customer" icon="users" subtitle="Add a customer to associate with licenses."
        :back="['href' => route('customers.index'), 'label' => 'Customers']" />

    <x-card>
        <form method="POST" action="{{ route('customers.store') }}" class="space-y-5">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-field label="Name" for="name" required :error="$errors->first('name')">
                    <x-input id="name" name="name" :value="old('name')" required autofocus placeholder="e.g. Jane Doe" />
                </x-field>
                <x-field label="Email" for="email" :error="$errors->first('email')">
                    <x-input id="email" name="email" type="email" :value="old('email')" placeholder="e.g. jane@example.com" />
                </x-field>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-field label="Company" for="company" :error="$errors->first('company')">
                    <x-input id="company" name="company" :value="old('company')" placeholder="e.g. Acme Inc." />
                </x-field>
                <x-field label="Phone" for="phone" :error="$errors->first('phone')">
                    <x-input id="phone" name="phone" :value="old('phone')" placeholder="e.g. +1 555 0100" />
                </x-field>
            </div>
            @isset($owners)
                @if ($owners->isNotEmpty())
                    <x-field label="Owner" for="owner_id" hint="User who owns this customer and their licenses." :error="$errors->first('owner_id')">
                        <x-select id="owner_id" name="owner_id">
                            <option value="">{{ auth()->user()->name }} (me)</option>
                            @foreach ($owners as $owner)
                                <option value="{{ $owner->id }}" @selected(old('owner_id') == $owner->id)>{{ $owner->name }} ({{ $owner->email }})</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="Also Visible To" hint="Extra users who can see this customer. Leave empty for the owner and admins only.">
                        <x-assignee-picker :users="$owners" :selected="[]" />
                    </x-field>
                @endif
            @endisset
            <x-field label="Notes" for="notes" :error="$errors->first('notes')">
                <textarea id="notes" name="notes" rows="3"
                    class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('notes') }}</textarea>
            </x-field>
            <div class="flex items-center justify-end gap-2 pt-1">
                <x-button variant="secondary" href="{{ route('customers.index') }}">Cancel</x-button>
                <x-button type="submit" icon="plus">Create Customer</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
