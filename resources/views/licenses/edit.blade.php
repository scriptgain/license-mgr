<x-layouts.app title="Edit License">
    <x-page-header title="Edit License" icon="license-key" :subtitle="$license->key"
        :back="['href' => route('licenses.show', $license), 'label' => 'License']" />

    <x-card>
        <form method="POST" action="{{ route('licenses.update', $license) }}" class="space-y-5">
            @csrf @method('PUT')
            <input type="hidden" name="product_id" value="{{ $license->product_id }}">
            <div class="rounded-lg bg-slate-50 ring-1 ring-inset ring-slate-200 px-3 py-2 text-sm text-slate-600">
                Product: <span class="font-medium text-slate-900">{{ optional($license->product)->name }}</span>
            </div>
            @include('licenses._fields', ['license' => $license])
            <div class="flex items-center justify-end gap-2 pt-1">
                <x-button variant="secondary" href="{{ route('licenses.show', $license) }}">Cancel</x-button>
                <x-button type="submit">Save Changes</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
