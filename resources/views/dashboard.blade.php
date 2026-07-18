@php
    use Illuminate\Support\Str;

    // KPI row — number + label + a meaningful one-line subtext, grouped with the icon.
    $activeShare = $stats['licenses'] ? (int) round($stats['active'] / $stats['licenses'] * 100) : 0;
    $kpis = [
        ['label' => 'Total Licenses', 'value' => number_format($stats['licenses']), 'icon' => 'license-key',
            'sub' => $expiringSoon ? $expiringSoon . ' expiring in 30 days' : 'All keys current',
            'tone' => $expiringSoon ? 'amber' : 'muted'],
        ['label' => 'Active', 'value' => number_format($stats['active']), 'icon' => 'check-circle',
            'sub' => $activeShare . '% of all licenses', 'tone' => 'emerald'],
        ['label' => 'Products', 'value' => number_format($stats['products']), 'icon' => 'archive',
            'sub' => 'In the catalog', 'tone' => 'muted'],
        ['label' => 'Customers', 'value' => number_format($stats['customers']), 'icon' => 'users',
            'sub' => number_format($activations24h) . ' ' . Str::plural('activation', $activations24h) . ' · 24h',
            'tone' => 'muted'],
    ];
    $toneClass = ['muted' => 'text-slate-400', 'amber' => 'text-amber-600', 'emerald' => 'text-emerald-600', 'rose' => 'text-rose-600'];

    // 14-day activation trend bar chart geometry (inline SVG, no chart library).
    $cw = 700; $ch = 150; $padT = 12; $padB = 22;
    $plotH = $ch - $padT - $padB;
    $baseY = $padT + $plotH;
    $n = max(1, count($activity));
    $slot = ($cw - 8) / $n;
    $barW = min(26, $slot * 0.62);
    $maxVal = max(1, max(array_column($activity, 'total') ?: [0]));

    // Active-license gauge (semicircle) geometry.
    $gaugeLen = 276.46; // ~ pi * r, r = 88
    $gaugeDash = round($activeShare / 100 * $gaugeLen, 1);

    // Status distribution donut geometry.
    $circ = 339.29; // 2 * pi * r, r = 54
    $segMap = [
        'active' => ['label' => 'Active', 'fill' => 'var(--color-brand-500)', 'dot' => 'mk-ok-bg'],
        'suspended' => ['label' => 'Suspended', 'fill' => '#f59e0b', 'dot' => 'bg-amber-500'],
        'expired' => ['label' => 'Expired', 'fill' => '#94a3b8', 'dot' => 'bg-slate-400'],
        'revoked' => ['label' => 'Revoked', 'fill' => '#f43f5e', 'dot' => 'bg-rose-500'],
    ];
    $segments = [];
    $accum = 0;
    foreach ($segMap as $key => $meta) {
        $val = $statusDist[$key] ?? 0;
        if ($distTotal > 0 && $val > 0) {
            $len = round($val / $distTotal * $circ, 2);
            $segments[] = ['fill' => $meta['fill'], 'len' => $len, 'offset' => round(-$accum, 2)];
            $accum += $len;
        }
    }
@endphp

<x-layouts.app title="Dashboard">
    {{-- Brand accent bound to the runtime --color-brand-* var so a custom accent still applies. --}}
    <style>
        .mk-ok-fill { fill: var(--color-brand-500); }
        .mk-ok-stroke { stroke: var(--color-brand-500); }
        .mk-ok-bg { background-color: var(--color-brand-500); }
    </style>

    <x-page-header title="Dashboard" subtitle="License operations at a glance.">
        <x-slot:actions>
            <x-button variant="secondary" size="sm" icon="server" href="{{ route('servers.index') }}">Servers</x-button>
            <x-button size="sm" icon="plus" href="{{ route('licenses.create') }}">Issue License</x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- KPI row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($kpis as $k)
            <div class="group relative flex flex-col overflow-hidden rounded-xl bg-white ring-1 ring-slate-200 shadow-sm transition hover:shadow-md hover:ring-brand-200">
                <span class="h-1 w-full bg-gradient-to-r from-brand-400 to-brand-600"></span>
                <div class="flex flex-1 items-center gap-4 p-5">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-brand-100">
                        <x-icon :name="$k['icon']" class="h-5 w-5" />
                    </span>
                    <div class="ml-auto text-right">
                        <p class="text-2xl font-semibold tracking-tight text-slate-900 tabular">{{ $k['value'] }}</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-600">{{ $k['label'] }}</p>
                        <p class="mt-0.5 text-xs font-medium {{ $toneClass[$k['tone']] }}">{{ $k['sub'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Activation trend + active-license gauge --}}
    <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
        {{-- Activation trend (signature visual) --}}
        <x-card title="Activation Trend" subtitle="New activations per day, last 14 days" class="lg:col-span-2">
            <x-slot:actions>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-200">
                    <x-icon name="check-circle" class="h-3.5 w-3.5" /> {{ number_format($activations24h) }} in 24h
                </span>
            </x-slot:actions>

            @if ($windowTotal === 0)
                <div class="flex h-40 flex-col items-center justify-center text-center">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-400"><x-icon name="key" class="h-5 w-5" /></span>
                    <p class="mt-3 text-sm text-slate-500">No activations recorded in the last 14 days.</p>
                </div>
            @else
                <svg viewBox="0 0 {{ $cw }} {{ $ch }}" width="100%" class="block h-auto" role="img" aria-label="Activations per day over the last 14 days">
                    <line x1="4" y1="{{ $baseY + 0.5 }}" x2="{{ $cw - 4 }}" y2="{{ $baseY + 0.5 }}" stroke="#e2e8f0" stroke-width="1" />
                    @foreach ($activity as $i => $d)
                        @php
                            $cx = 4 + $slot * $i + $slot / 2;
                            $x = round($cx - $barW / 2, 1);
                            $h = $d['total'] ? max(3, round($d['total'] / $maxVal * $plotH, 1)) : 0;
                        @endphp
                        @if ($h === 0.0 || $h === 0)
                            <rect x="{{ $x }}" y="{{ $baseY - 3 }}" width="{{ round($barW, 1) }}" height="3" rx="1.5" fill="#e2e8f0" />
                        @else
                            <rect x="{{ $x }}" y="{{ round($baseY - $h, 1) }}" width="{{ round($barW, 1) }}" height="{{ $h }}" rx="2" class="mk-ok-fill" />
                        @endif
                        @if ($i === 0 || $i === intdiv($n, 2) || $i === $n - 1)
                            <text x="{{ round($cx, 1) }}" y="{{ $ch - 6 }}" text-anchor="{{ $i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle') }}" fill="#94a3b8" style="font-size:11px">{{ $d['label'] }}</text>
                        @endif
                    @endforeach
                </svg>

                <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs font-medium text-slate-500">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm mk-ok-bg"></span> Device activations</span>
                    <span class="ml-auto tabular text-slate-400">{{ number_format($windowTotal) }} {{ Str::plural('activation', $windowTotal) }} total</span>
                </div>
            @endif

            <x-slot:footer>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg {{ $expiringSoon ? 'bg-amber-50 text-amber-600 ring-1 ring-amber-100' : 'bg-white text-slate-400 ring-1 ring-slate-200' }}"><x-icon name="clock" class="h-4 w-4" /></span>
                        <div>
                            <p class="text-lg font-semibold leading-tight tabular {{ $expiringSoon ? 'text-amber-600' : 'text-slate-900' }}">{{ $expiringSoon }}</p>
                            <p class="text-xs text-slate-500">Expiring · 30 days</p>
                        </div>
                    </div>
                    <span class="h-9 w-px bg-slate-200"></span>
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg {{ $revoked ? 'bg-rose-50 text-rose-600 ring-1 ring-rose-100' : 'bg-white text-slate-400 ring-1 ring-slate-200' }}"><x-icon name="x-circle" class="h-4 w-4" /></span>
                        <div>
                            <p class="text-lg font-semibold leading-tight tabular {{ $revoked ? 'text-rose-600' : 'text-slate-900' }}">{{ $revoked }}</p>
                            <p class="text-xs text-slate-500">Revoked keys</p>
                        </div>
                    </div>
                </div>
            </x-slot:footer>
        </x-card>

        {{-- Active-license gauge --}}
        <x-card title="Active Licenses" subtitle="Share of all issued keys">
            <div>
                <div class="mx-auto w-full max-w-[240px]">
                    <svg viewBox="0 0 200 122" width="100%" role="img" aria-label="{{ $activeShare }} percent of licenses active">
                        <path d="M12 110 A88 88 0 0 1 188 110" fill="none" stroke="#e2e8f0" stroke-width="14" stroke-linecap="round" />
                        <path d="M12 110 A88 88 0 0 1 188 110" fill="none" stroke-width="14" stroke-linecap="round"
                            class="mk-ok-stroke" stroke-dasharray="{{ $gaugeDash }} 1000" />
                        <text x="100" y="92" text-anchor="middle" fill="#0f172a" style="font-size:38px;font-weight:700;font-variant-numeric:tabular-nums">{{ $activeShare }}%</text>
                        <text x="100" y="110" text-anchor="middle" fill="#94a3b8" style="font-size:11px;letter-spacing:.02em">active</text>
                    </svg>
                </div>
                <div class="mt-1 flex items-baseline justify-between">
                    <span class="text-lg font-semibold text-slate-900 tabular">{{ number_format($stats['active']) }}</span>
                    <span class="text-sm text-slate-500 tabular">of {{ number_format($stats['licenses']) }} keys</span>
                </div>
                <p class="mt-1 text-xs text-slate-400">
                    {{ number_format($stats['customers']) }} {{ Str::plural('customer', $stats['customers']) }} ·
                    {{ number_format($stats['products']) }} {{ Str::plural('product', $stats['products']) }}
                </p>
            </div>
        </x-card>
    </div>

    {{-- Status distribution + recent licenses --}}
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {{-- License status distribution donut --}}
        <x-card title="License Status" subtitle="Distribution by state">
            @if ($distTotal === 0)
                <x-empty-state icon="license-key" title="No Licenses" description="Issue a license to see its status here." />
            @else
                <div class="relative mx-auto w-full max-w-[180px]">
                    <svg viewBox="0 0 140 140" width="100%" role="img" aria-label="License status distribution">
                        <circle cx="70" cy="70" r="54" fill="none" stroke="#eef2f6" stroke-width="18" />
                        @foreach ($segments as $s)
                            <circle cx="70" cy="70" r="54" fill="none" stroke="{{ $s['fill'] }}" stroke-width="18"
                                stroke-dasharray="{{ $s['len'] }} {{ $circ }}" stroke-dashoffset="{{ $s['offset'] }}"
                                transform="rotate(-90 70 70)" stroke-linecap="butt" />
                        @endforeach
                        <text x="70" y="66" text-anchor="middle" fill="#0f172a" style="font-size:26px;font-weight:700;font-variant-numeric:tabular-nums">{{ number_format($distTotal) }}</text>
                        <text x="70" y="84" text-anchor="middle" fill="#94a3b8" style="font-size:10px;letter-spacing:.04em">LICENSES</text>
                    </svg>
                </div>
                <ul class="mt-4 space-y-2">
                    @foreach ($segMap as $key => $meta)
                        <li class="flex items-center justify-between text-sm">
                            <span class="inline-flex items-center gap-2 text-slate-600">
                                <span class="h-2.5 w-2.5 rounded-sm {{ $meta['dot'] }}"></span> {{ $meta['label'] }}
                            </span>
                            <span class="tabular font-medium text-slate-900">{{ number_format($statusDist[$key] ?? 0) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Recent licenses (restyled — flush table, product pills, status dots, customer avatars) --}}
        <div class="lg:col-span-2">
            <x-card title="Recent Licenses" :flush="! $recent->isEmpty()">
                <x-slot:actions>
                    <a href="{{ route('licenses.index') }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">View All</a>
                </x-slot:actions>
                @if ($recent->isEmpty())
                    <x-empty-state icon="license-key" title="No Licenses Yet" description="Issue your first license key.">
                        <x-slot:action><x-button icon="plus" href="{{ route('licenses.create') }}">Issue License</x-button></x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead><tr><th>Key</th><th>Product</th><th>Status</th><th>Customer</th></tr></thead>
                        <tbody>
                            @foreach ($recent as $l)
                                @php
                                    $cust = $l->customer_email ?: (optional($l->customer)->name ?: '—');
                                    $init = strtoupper(mb_substr(trim($cust), 0, 1)) ?: '?';
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('licenses.show', $l) }}" class="font-mono text-xs tracking-tight text-slate-600 hover:text-brand-700">{{ $l->key }}</a>
                                    </td>
                                    <td>
                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-200">{{ optional($l->product)->name ?? '—' }}</span>
                                    </td>
                                    <td><x-badge :color="['active' => 'success', 'suspended' => 'warn', 'revoked' => 'danger', 'expired' => 'neutral'][$l->effectiveStatus()] ?? 'neutral'" dot>{{ $l->statusLabel() }}</x-badge></td>
                                    <td>
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-100 text-[10px] font-semibold uppercase text-brand-700">{{ $init }}</span>
                                            <span class="truncate text-slate-600">{{ $cust }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>
    </div>

    {{-- License servers --}}
    <div class="mt-6">
        <x-card title="License Servers" subtitle="Verification nodes" :flush="! $servers->isEmpty()">
            <x-slot:actions>
                <a href="{{ route('servers.index') }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">View All</a>
            </x-slot:actions>
            @if ($servers->isEmpty())
                <x-empty-state icon="server" title="No Servers" description="Add a verification node.">
                    <x-slot:action><x-button size="sm" icon="plus" href="{{ route('servers.create') }}">Add Server</x-button></x-slot:action>
                </x-empty-state>
            @else
                <x-table flush>
                    <thead><tr><th>Name</th><th>Status</th><th class="text-right">Licenses</th><th class="text-right">Last Synced</th></tr></thead>
                    <tbody>
                        @foreach ($servers as $s)
                            <tr class="cursor-pointer" onclick="window.location='{{ route('servers.show', $s) }}'">
                                <td>
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-brand-100"><x-icon name="server" class="h-4 w-4" /></span>
                                        <div class="min-w-0">
                                            <div class="font-medium text-slate-900 truncate">{{ $s->name }}</div>
                                            @if (optional($s->location)->name)
                                                <div class="text-xs text-slate-500 truncate">{{ $s->location->name }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($s->isOnline())
                                        <x-badge color="success" dot>Online</x-badge>
                                    @else
                                        <x-badge :color="['active' => 'success', 'pending' => 'warn', 'disabled' => 'neutral'][$s->status] ?? 'neutral'" dot>{{ $s->statusLabel() }}</x-badge>
                                    @endif
                                </td>
                                <td class="text-right tabular text-slate-600">{{ number_format($s->license_count) }}</td>
                                <td class="text-right text-slate-500" data-tip="{{ optional($s->last_sync_at)?->format('M j, Y g:i A') }}">{{ optional($s->last_sync_at)->diffForHumans() ?? 'Never' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif
        </x-card>
    </div>
</x-layouts.app>
