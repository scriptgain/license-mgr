<?php

namespace Database\Seeders;

use App\Models\Activation;
use App\Models\Customer;
use App\Models\License;
use App\Models\LicenseServer;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Read-only public demo data for the licensing control panel: a vendor's own
 * product catalog, customers, issued license keys with activations, and
 * verification servers. Idempotent. Never run on a real install.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['activations', 'licenses', 'plans', 'products', 'license_servers', 'customers', 'locations'] as $t) {
            if (DB::getSchemaBuilder()->hasTable($t)) {
                DB::table($t)->truncate();
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $admin = User::updateOrCreate(['email' => 'demo@scriptgain.com'],
            ['name' => 'Demo Admin', 'password' => Hash::make(Str::random(40)), 'role' => 'admin', 'email_verified_at' => now()]);
        User::updateOrCreate(['email' => 'ops@scriptgain.com'],
            ['name' => 'Jordan Kim', 'password' => Hash::make(Str::random(40)), 'role' => 'operator', 'email_verified_at' => now()]);
        $uid = $admin->id;
        Setting::updateOrCreate(['key' => 'setup_complete'], ['value' => '1']);

        // Products the vendor licenses to their own customers
        $products = [];
        foreach ([
            ['Pro Desktop App', 'pro-desktop', 'Flagship cross-platform desktop application.', 'PDX', 3, 365],
            ['Mobile SDK', 'mobile-sdk', 'Embeddable SDK for iOS and Android apps.', 'MSDK', 5, 365],
            ['Enterprise Server', 'ent-server', 'Self-hosted server product for large teams.', 'ENT', 10, 365],
        ] as [$n, $s, $d, $kp, $ma, $ex]) {
            $p = Product::create(['name' => $n, 'slug' => $s, 'description' => $d, 'key_prefix' => $kp, 'default_max_activations' => $ma, 'default_expiry_days' => $ex]);
            Plan::create(['product_id' => $p->id, 'name' => 'Standard', 'slug' => $s.'-standard', 'price' => 49, 'interval' => 'yearly', 'max_activations' => $ma, 'expiry_days' => $ex]);
            Plan::create(['product_id' => $p->id, 'name' => 'Business', 'slug' => $s.'-business', 'price' => 149, 'interval' => 'yearly', 'max_activations' => $ma * 3, 'expiry_days' => $ex]);
            $products[] = $p;
        }

        // Locations + verification servers (nodes)
        foreach ([
            ['US East', 'Ashburn, Virginia', 'us-east-1', true],
            ['EU Central', 'Frankfurt, Germany', 'eu-central-1', false],
            ['APAC', 'Singapore', 'ap-southeast-1', false],
        ] as $i => [$n, $a, $r, $def]) {
            $loc = Location::create(['name' => $n, 'slug' => Str::slug($n), 'address' => $a, 'region' => $r, 'is_default' => $def]);
            LicenseServer::create([
                'location_id' => $loc->id, 'user_id' => $uid, 'name' => 'verify-node-'.($i + 1), 'is_local' => $def,
                'hostname' => 'verify'.($i + 1).'.internal', 'enroll_token' => 'et_'.Str::random(24),
                'status' => 'online', 'ip' => '10.0.'.($i + 1).'.10', 'agent_version' => '1.1.0',
                'last_seen_at' => now()->subSeconds(random_int(5, 90)), 'last_sync_at' => now()->subMinutes(random_int(1, 10)),
                'license_count' => random_int(4, 28),
            ]);
        }

        $keyChars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $mkKey = function ($prefix) use ($keyChars) {
            $g = fn () => collect(range(1, 4))->map(fn () => $keyChars[random_int(0, strlen($keyChars) - 1)])->implode('');
            return $prefix.'-'.$g().'-'.$g().'-'.$g();
        };
        $statuses = ['active', 'active', 'active', 'active', 'active', 'active', 'expired', 'suspended', 'active', 'active'];
        $companies = [
            ['Acme Corp', 'acme.example'], ['Globex LLC', 'globex.example'], ['Initech', 'initech.example'],
            ['Umbrella Co', 'umbrella.example'], ['Wayne Enterprises', 'wayne.example'], ['Stark Industries', 'stark.example'],
            ['Hooli', 'hooli.example'], ['Pied Piper', 'piedpiper.example'], ['Soylent', 'soylent.example'], ['Wonka Inc', 'wonka.example'],
        ];

        $totalLic = 0; $totalAct = 0;
        foreach ($companies as $ci => [$cn, $dom]) {
            $email = 'billing@'.$dom;
            $cust = Customer::create(['user_id' => $uid, 'name' => $cn, 'email' => $email, 'company' => $cn]);
            for ($k = 0; $k < random_int(1, 3); $k++) {
                $prod = $products[array_rand($products)];
                $status = $statuses[($ci + $k) % count($statuses)];
                $ma = random_int(1, 10);
                $lic = License::create([
                    'product_id' => $prod->id, 'customer_id' => $cust->id, 'key' => $mkKey($prod->key_prefix),
                    'status' => $status, 'max_activations' => $ma,
                    'expires_at' => $status === 'expired' ? now()->subDays(random_int(5, 60)) : now()->addDays(random_int(30, 400)),
                    'entitlements' => ['features' => ['api', 'priority-support']],
                    'customer_name' => $cn, 'customer_email' => $email, 'signed_at' => now()->subDays(random_int(1, 120)),
                ]);
                $totalLic++;
                $actN = $status === 'active' ? random_int(0, $ma) : 0;
                for ($a = 0; $a < $actN; $a++) {
                    Activation::create([
                        'license_id' => $lic->id, 'fingerprint' => (string) Str::uuid(),
                        'hostname' => 'host-'.Str::random(4).'.'.$dom,
                        'ip' => random_int(1, 223).'.'.random_int(0, 255).'.'.random_int(0, 255).'.'.random_int(1, 254),
                        'user_agent' => 'ProDesktop/2.4 (Windows NT 10.0; Win64; x64)',
                        'last_seen_at' => now()->subHours(random_int(0, 72)),
                    ]);
                    $totalAct++;
                }
            }
        }

        $this->command?->info('License demo seeded: '.Customer::count()." customers, {$totalLic} licenses, {$totalAct} activations.");
    }
}
