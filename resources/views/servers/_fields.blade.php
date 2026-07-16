{{-- Shared license-server fields. Expects $server (nullable), $locations. --}}
@php $s = $server ?? null; $inp = 'block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500'; @endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <x-field label="Name" for="name" required :error="$errors->first('name')">
        <x-input id="name" name="name" :value="old('name', $s?->name)" required autofocus placeholder="e.g. node-us-east" />
    </x-field>
    <x-field label="Location" for="location_id" :error="$errors->first('location_id')">
        <select id="location_id" name="location_id" class="{{ $inp }}">
            <option value="">— None —</option>
            @foreach ($locations as $loc)
                <option value="{{ $loc->id }}" @selected(old('location_id', $s?->location_id ?? ($defaultLocationId ?? null)) == $loc->id)>{{ $loc->name }}@if($loc->is_default) (default)@endif</option>
            @endforeach
        </select>
    </x-field>
</div>

<x-field label="Public Hostname / URL" for="hostname" hint="Where this node's validation API is reachable, e.g. https://node1.example.com:8787" :error="$errors->first('hostname')">
    <x-input id="hostname" name="hostname" :value="old('hostname', $s?->hostname)" placeholder="https://node1.example.com:8787" />
</x-field>

<x-field label="Status" for="status" required :error="$errors->first('status')">
    <select id="status" name="status" class="{{ $inp }}">
        @foreach (\App\Models\LicenseServer::STATUSES as $sc => $sl)
            <option value="{{ $sc }}" @selected(old('status', $s?->status ?? 'pending') === $sc)>{{ $sl }}</option>
        @endforeach
    </select>
</x-field>

@isset($owners)
    @if ($owners->isNotEmpty())
        <x-field label="Owner" for="owner_id" hint="User who owns and manages this node." :error="$errors->first('owner_id')">
            <select id="owner_id" name="owner_id" class="{{ $inp }}">
                <option value="">{{ auth()->user()->name }} (me)</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected(old('owner_id', $s?->user_id) == $owner->id)>{{ $owner->name }} ({{ $owner->email }})</option>
                @endforeach
            </select>
        </x-field>
        <x-field label="Also Visible To" hint="Extra users who can see this node. Leave empty for the owner and admins only.">
            <x-assignee-picker :users="$owners" :selected="$s?->assignees?->pluck('id')->all() ?? []" />
        </x-field>
    @endif
@endisset

<x-field label="Notes" for="notes" :error="$errors->first('notes')">
    <textarea id="notes" name="notes" rows="2" class="{{ $inp }}">{{ old('notes', $s?->notes) }}</textarea>
</x-field>
