<?php

namespace App\Console\Commands;

use App\Http\Controllers\MaintenanceController;
use App\Models\AuditLog;
use Illuminate\Console\Command;

class LicenseMaintenance extends Command
{
    protected $signature = 'license:maintenance {--force : Ignore the configured maintenance window}';

    protected $description = 'Expire past-due licenses, reclaim stale activation seats, and prune old audit rows.';

    public function handle(): int
    {
        if (! $this->option('force') && ! MaintenanceController::allowedNow()) {
            $this->info('Outside the maintenance window; skipping. Use --force to override.');

            return self::SUCCESS;
        }

        $c = MaintenanceController::runSweep();

        $this->info("Maintenance: {$c['expired']} license(s) expired, {$c['activations_reaped']} activation(s) reclaimed, {$c['audit_pruned']} audit row(s) pruned.");
        AuditLog::record('maintenance', "Scheduled maintenance: {$c['expired']} expired, {$c['activations_reaped']} activations reaped, {$c['audit_pruned']} audit rows pruned");

        return self::SUCCESS;
    }
}
