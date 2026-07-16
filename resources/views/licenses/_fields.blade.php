{{-- Shared license fields. Expects $license (nullable), $products, $customers. --}}
@php $l = $license ?? null; $inp = 'block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500'; @endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <x-field label="Plan" for="plan_id" hint="Sets entitlements, seats & term. Optional." :error="$errors->first('plan_id')">
        <select id="plan_id" name="plan_id" class="{{ $inp }}">
            <option value="">— No plan —</option>
            @foreach ($products as $p)
                <optgroup label="{{ $p->name }}">
                    @foreach ($p->plans as $pl)
                        <option value="{{ $pl->id }}" @selected(old('plan_id', $l?->plan_id) == $pl->id)>{{ $pl->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </x-field>
    <x-field label="Max Activations" for="max_activations" hint="0 = unlimited." required :error="$errors->first('max_activations')">
        <x-input id="max_activations" name="max_activations" type="number" min="0" :value="old('max_activations', $l?->max_activations ?? 1)" required />
    </x-field>
</div>

<x-field label="Customer" for="customer_id" hint="Link to an existing customer, or fill in the name/email below." :error="$errors->first('customer_id')">
    <select id="customer_id" name="customer_id" class="{{ $inp }}">
        <option value="">— None —</option>
        @foreach ($customers as $c)
            <option value="{{ $c->id }}" @selected(old('customer_id', $l?->customer_id) == $c->id)>{{ $c->name }}@if($c->email) ({{ $c->email }})@endif</option>
        @endforeach
    </select>
</x-field>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <x-field label="Customer Name" for="customer_name" :error="$errors->first('customer_name')">
        <x-input id="customer_name" name="customer_name" :value="old('customer_name', $l?->customer_name)" />
    </x-field>
    <x-field label="Customer Email" for="customer_email" :error="$errors->first('customer_email')">
        <x-input id="customer_email" name="customer_email" type="email" :value="old('customer_email', $l?->customer_email)" />
    </x-field>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <x-field label="Expires On" for="expires_at" hint="Blank = perpetual (or plan default on create)." :error="$errors->first('expires_at')">
        <x-input id="expires_at" name="expires_at" type="date" :value="old('expires_at', optional($l?->expires_at)->format('Y-m-d'))" />
    </x-field>
</div>

<x-field label="Notes" for="notes" :error="$errors->first('notes')">
    <textarea id="notes" name="notes" rows="3" class="{{ $inp }}">{{ old('notes', $l?->notes) }}</textarea>
</x-field>
