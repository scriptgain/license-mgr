<?php

namespace App\Http\Controllers;

use App\Models\Activation;
use App\Models\Customer;
use App\Models\License;
use App\Models\LicenseServer;

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
            'servers' => LicenseServer::visibleTo($user)->where('status', 'active')->count(),
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

        return view('dashboard', compact('stats', 'expiringSoon', 'revoked', 'activations24h', 'recent', 'servers'));
    }
}
