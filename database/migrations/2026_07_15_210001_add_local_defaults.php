<?php

use App\Models\LicenseServer;
use App\Models\Location;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ship a working default out of the box: a "Local" location and the control
 * panel itself as the built-in (free) license server. Issuing licenses to this
 * one node works immediately; additional nodes require license entitlement
 * (`license_server_limit`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('slug');
        });
        Schema::table('license_servers', function (Blueprint $table) {
            $table->boolean('is_local')->default(false)->after('name');
        });

        $local = Location::firstOrCreate(
            ['slug' => 'local'],
            ['name' => 'Local', 'is_default' => true]
        );
        $local->update(['is_default' => true]);

        LicenseServer::firstOrCreate(
            ['is_local' => true],
            [
                'name' => 'Main Panel',
                'location_id' => $local->id,
                'status' => 'active',
                'hostname' => rtrim(config('app.url'), '/'),
                'notes' => 'This control panel. It validates licenses directly — no installer needed.',
            ]
        );

        // Additional (non-local) servers allowed by this instance's license.
        // Driven by the instance license entitlement; 0 until licensed for more.
        if (Setting::get('license_server_limit') === null) {
            Setting::put('license_server_limit', '0');
        }
    }

    public function down(): void
    {
        Schema::table('locations', fn (Blueprint $t) => $t->dropColumn('is_default'));
        Schema::table('license_servers', fn (Blueprint $t) => $t->dropColumn('is_local'));
    }
};
