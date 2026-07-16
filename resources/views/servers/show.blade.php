<x-layouts.app :title="$server->name">
    <x-page-header :title="$server->name" icon="server"
        :subtitle="optional($server->location)->name"
        :back="['href' => route('servers.index'), 'label' => 'License Servers']">
        <x-slot:actions>
            <x-button variant="secondary" icon="edit" href="{{ route('servers.edit', $server) }}">Edit</x-button>
            @unless ($server->is_local)
                <x-delete-button :name="'del-srv'" :action="route('servers.destroy', $server)"
                    title="Remove License Server?" message="The node stops receiving license updates." />
            @endunless
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            @if ($server->is_local)
                <x-card title="Built-in License Server">
                    <p class="text-sm text-slate-600">This is the control panel itself. It holds every valid license and answers validations directly — no installer or enrollment needed. It's included with your instance and doesn't count against your license-server entitlement.</p>
                    <div class="mt-4 space-y-3">
                        <div>
                            <p class="text-xs text-slate-500 mb-1">Validation endpoint</p>
                            <code class="block rounded-lg bg-slate-100 ring-1 ring-inset ring-slate-200 px-3 py-2 text-xs font-mono break-all">POST {{ rtrim(config('app.url'), '/') }}/api/v1/validate</code>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-1">Public key (for offline verification)</p>
                            <code class="block rounded-lg bg-slate-100 ring-1 ring-inset ring-slate-200 px-3 py-2 text-xs font-mono break-all">GET {{ rtrim(config('app.url'), '/') }}/api/v1/public-key</code>
                        </div>
                    </div>
                </x-card>
            @else
                <x-card title="Install This Node">
                    <p class="text-sm text-slate-600 mb-3">Run this on the server you want to act as a verification node. It installs the sync agent (pulls every valid license on a timer) and a local validation API.</p>
                    <div x-data="{ copied: false }" class="relative">
                        <pre class="overflow-x-auto rounded-lg bg-slate-900 text-slate-100 text-xs leading-relaxed p-4 pr-12 font-mono">{{ $installCommand }}</pre>
                        <button type="button"
                            @click="navigator.clipboard.writeText(@js($installCommand)); copied = true; setTimeout(() => copied = false, 1500)"
                            class="absolute top-2.5 right-2.5 inline-flex items-center gap-1 rounded-md bg-white/10 px-2 py-1 text-xs text-slate-200 hover:bg-white/20 transition">
                            <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied</span>
                        </button>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">After it registers, this node moves from <x-badge color="warn">Pending</x-badge> to <x-badge color="success">Active</x-badge> on its first sync.</p>
                </x-card>

                <x-card title="Enrollment Token">
                    <p class="text-sm text-slate-600 mb-3">The node authenticates its license pulls with this token. Keep it secret; regenerating it requires re-running the installer.</p>
                    <div class="flex flex-wrap items-center gap-3" x-data="{ show: false }">
                        <code class="rounded-lg bg-slate-100 ring-1 ring-inset ring-slate-200 px-3 py-2 text-xs font-mono break-all" x-text="show ? @js($server->enroll_token) : '{{ str_repeat('•', 24) }}'"></code>
                        <x-button variant="secondary" size="sm" x-on:click="show = !show"><span x-text="show ? 'Hide' : 'Reveal'"></span></x-button>
                        <form method="POST" action="{{ route('servers.regenerate', $server) }}">
                            @csrf
                            <x-button type="submit" variant="secondary" size="sm" icon="refresh">Regenerate</x-button>
                        </form>
                    </div>
                </x-card>
            @endif
        </div>

        {{-- Status + key --}}
        <div class="space-y-6">
            <x-card title="Status">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd><x-badge :color="['active' => 'success', 'pending' => 'warn', 'disabled' => 'neutral'][$server->status] ?? 'neutral'">{{ $server->statusLabel() }}</x-badge></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Reachability</dt><dd>@if($server->is_local)<x-badge color="success" dot>This panel</x-badge>@elseif($server->isOnline())<x-badge color="success" dot>Online</x-badge>@else<span class="text-slate-400">Offline</span>@endif</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Licenses Held</dt><dd class="tabular text-slate-900">{{ $server->is_local ? $replicable : $server->license_count }}</dd></div>
                    @unless ($server->is_local)
                        <div class="flex justify-between"><dt class="text-slate-500">Last Sync</dt><dd class="text-slate-900">{{ optional($server->last_sync_at)->diffForHumans() ?? 'Never' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Last Seen</dt><dd class="text-slate-900">{{ optional($server->last_seen_at)->diffForHumans() ?? 'Never' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Agent</dt><dd class="text-slate-900">{{ $server->agent_version ?: '—' }}</dd></div>
                    @endunless
                </dl>
            </x-card>
            <x-card title="Signing Key">
                <p class="text-sm text-slate-600 mb-2">Nodes verify license signatures against this instance's public key.</p>
                <p class="text-xs text-slate-500">Fingerprint (SHA-256)</p>
                <code class="mt-1 block rounded-lg bg-slate-100 ring-1 ring-inset ring-slate-200 px-3 py-2 text-xs font-mono break-all">{{ $fingerprint }}</code>
            </x-card>
        </div>
    </div>
</x-layouts.app>
