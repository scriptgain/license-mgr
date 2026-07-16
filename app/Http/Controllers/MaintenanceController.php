<?php

namespace App\Http\Controllers;

use App\Models\Activation;
use App\Models\AuditLog;
use App\Models\License;
use App\Models\Setting;
use App\Services\LicenseIssuer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    /** Ordered day-of-week tokens matching Carbon's lowercase `D` format. */
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** Defaults for every Maintenance setting. Keys are Setting table keys. */
    public static function defaults(): array
    {
        return [
            // Master switch for the scheduled housekeeping sweep.
            'auto_maintenance' => '1',
            // Confine the sweep to off-peak hours.
            'maintenance_window_enabled' => '0',
            'maintenance_window_start' => '02:00',
            'maintenance_window_end' => '05:00',
            'maintenance_days' => implode(',', self::DAYS),
            // What the sweep does.
            'expire_licenses' => '1',
            'reap_stale_activations' => '0',
            'activation_stale_days' => '90',
            'audit_log_days' => '180',
        ];
    }

    /** Read current values merged over defaults. */
    public static function values(): array
    {
        $map = Setting::map();
        $v = [];
        foreach (static::defaults() as $key => $default) {
            $v[$key] = $map[$key] ?? $default;
        }

        return $v;
    }

    /**
     * Whether the scheduled sweep may run right now, honoring the window.
     * Stateless: evaluated against the current time in the app timezone.
     */
    public static function allowedNow(?array $s = null, ?\DateTimeInterface $now = null): bool
    {
        $s ??= static::values();
        if (($s['auto_maintenance'] ?? '1') !== '1') {
            return false;
        }
        if (($s['maintenance_window_enabled'] ?? '0') !== '1') {
            return true;
        }

        $now = $now ? \Illuminate\Support\Carbon::instance($now) : now();

        $days = array_filter(explode(',', $s['maintenance_days'] ?? ''));
        if ($days && ! in_array(strtolower($now->format('D')), $days, true)) {
            return false;
        }

        $start = $s['maintenance_window_start'] ?? '00:00';
        $end = $s['maintenance_window_end'] ?? '23:59';
        $cur = $now->format('H:i');

        return $start <= $end
            ? ($cur >= $start && $cur <= $end)
            : ($cur >= $start || $cur <= $end);
    }

    /**
     * Run the housekeeping sweep. Returns per-task counts. Used by both the
     * scheduled command and the manual "Run Now" button.
     */
    public static function runSweep(?array $s = null): array
    {
        $s ??= static::values();
        $counts = ['expired' => 0, 'activations_reaped' => 0, 'audit_pruned' => 0];

        // 1. Persist date-expiry: active licenses past their expiry become expired.
        if (($s['expire_licenses'] ?? '1') === '1') {
            License::where('status', 'active')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->get()
                ->each(function (License $license) use (&$counts) {
                    $license->update(['status' => 'expired']);
                    LicenseIssuer::resign($license);
                    $counts['expired']++;
                });
        }

        // 2. Reclaim seats: drop activations not seen within the stale window.
        if (($s['reap_stale_activations'] ?? '0') === '1') {
            $days = max(1, (int) ($s['activation_stale_days'] ?? 90));
            $counts['activations_reaped'] = Activation::whereNotNull('last_seen_at')
                ->where('last_seen_at', '<', now()->subDays($days))
                ->delete();
        }

        // 3. Prune old audit rows.
        $auditDays = (int) ($s['audit_log_days'] ?? 180);
        if ($auditDays > 0) {
            $counts['audit_pruned'] = AuditLog::where('created_at', '<', now()->subDays($auditDays))->delete();
        }

        return $counts;
    }

    public function edit()
    {
        $v = static::values();

        return view('settings.maintenance', [
            'v' => $v,
            'days' => self::DAYS,
            'selectedDays' => array_filter(explode(',', $v['maintenance_days'])),
            'allowedNow' => static::allowedNow($v),
            'now' => now(),
            'stats' => [
                'Active Licenses' => License::where('status', 'active')->count(),
                'Expiring (past due, still active)' => License::where('status', 'active')
                    ->whereNotNull('expires_at')->where('expires_at', '<', now())->count(),
                'Total Activations' => Activation::count(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'maintenance_window_start' => ['required', 'date_format:H:i'],
            'maintenance_window_end' => ['required', 'date_format:H:i'],
            'maintenance_days' => ['nullable', 'array'],
            'maintenance_days.*' => [Rule::in(self::DAYS)],
            'activation_stale_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'audit_log_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);

        foreach (['auto_maintenance', 'maintenance_window_enabled', 'expire_licenses', 'reap_stale_activations'] as $t) {
            Setting::put($t, $request->boolean($t) ? '1' : '0');
        }

        Setting::put('maintenance_window_start', $data['maintenance_window_start']);
        Setting::put('maintenance_window_end', $data['maintenance_window_end']);
        Setting::put('maintenance_days', implode(',', $data['maintenance_days'] ?? []));
        Setting::put('activation_stale_days', (string) $data['activation_stale_days']);
        Setting::put('audit_log_days', (string) $data['audit_log_days']);

        AuditLog::record('updated', 'Maintenance settings updated');

        return back()->with('status', 'Maintenance settings saved.');
    }

    /** Manual sweep, bypassing the window. */
    public function runNow()
    {
        $c = static::runSweep();
        AuditLog::record('maintenance', "Manual maintenance: {$c['expired']} expired, {$c['activations_reaped']} activations reaped, {$c['audit_pruned']} audit rows pruned");

        return back()->with('status', "Maintenance ran: {$c['expired']} license(s) expired, {$c['activations_reaped']} activation(s) reclaimed, {$c['audit_pruned']} audit row(s) pruned.");
    }
}
