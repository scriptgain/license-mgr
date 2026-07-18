<?php

namespace App\Http\Controllers;

use App\Models\Activation;
use App\Models\Customer;
use App\Models\License;
use App\Models\LicenseServer;
use App\Models\Product;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        // Everything on the dashboard is scoped to what the user may see.
        $activeQuery = fn () => License::visibleTo($user)->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));

        $stats = [
            'licenses' => License::visibleTo($user)->count(),
            'active' => $activeQuery()->count(),
            'products' => Product::count(),
            'customers' => Customer::visibleTo($user)->count(),
        ];

        $expiringSoon = License::visibleTo($user)->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->count();

        $revoked = License::visibleTo($user)->where('status', 'revoked')->count();
        $activations24h = Activation::visibleTo($user)->where('last_seen_at', '>=', now()->subDay())->count();

        $recent = License::visibleTo($user)->with('product', 'customer')->latest()->limit(8)->get();
        $servers = LicenseServer::visibleTo($user)->with('location')->latest()->limit(6)->get();

        // License status distribution (effective status resolved in PHP for portability).
        $dist = License::visibleTo($user)->get(['status', 'expires_at'])
            ->groupBy(fn ($l) => $l->effectiveStatus())->map->count();
        $statusDist = [
            'active' => (int) ($dist['active'] ?? 0),
            'suspended' => (int) ($dist['suspended'] ?? 0),
            'expired' => (int) ($dist['expired'] ?? 0),
            'revoked' => (int) ($dist['revoked'] ?? 0),
        ];
        $distTotal = array_sum($statusDist);

        // 14-day activation trend. One query, bucketed per day in PHP so it stays
        // portable across SQLite/MySQL.
        $since = now()->subDays(13)->startOfDay();
        $recentActivations = Activation::visibleTo($user)
            ->where('created_at', '>=', $since)
            ->get(['created_at']);

        $activity = collect(range(0, 13))->map(function ($i) use ($recentActivations) {
            $day = now()->subDays(13 - $i)->startOfDay();
            $next = $day->copy()->addDay();

            return [
                'label' => $day->format('M j'),
                'total' => $recentActivations->filter(fn ($a) => $a->created_at >= $day && $a->created_at < $next)->count(),
            ];
        })->all();

        $windowTotal = (int) array_sum(array_column($activity, 'total'));

        return view('dashboard', compact(
            'stats', 'expiringSoon', 'revoked', 'activations24h', 'recent', 'servers',
            'statusDist', 'distTotal', 'activity', 'windowTotal',
        ));
    }
}
