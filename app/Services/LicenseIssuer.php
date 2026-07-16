<?php

namespace App\Services;

use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use Illuminate\Support\Carbon;

/**
 * Creates a license: key generation, entitlement resolution (from the chosen
 * plan's features), term, and RSA signing. Reused by the dashboard and API.
 */
class LicenseIssuer
{
    /**
     * @param  array{product_id:int,plan_id?:int,customer_id?:int,max_activations?:int,
     *   expires_at?:string,expires_days?:int,entitlements?:array,customer_name?:string,
     *   customer_email?:string,notes?:string,status?:string}  $data
     */
    public static function issue(array $data): License
    {
        $product = Product::findOrFail($data['product_id']);
        $plan = ! empty($data['plan_id']) ? Plan::with('features')->find($data['plan_id']) : null;

        $expires = null;
        if (! empty($data['expires_at'])) {
            $expires = Carbon::parse($data['expires_at']);
        } elseif (! empty($data['expires_days'])) {
            $expires = Carbon::now()->addDays((int) $data['expires_days']);
        } elseif ($plan && $plan->expiry_days) {
            $expires = Carbon::now()->addDays($plan->expiry_days);
        } elseif ($product->default_expiry_days) {
            $expires = Carbon::now()->addDays($product->default_expiry_days);
        }

        $entitlements = $data['entitlements']
            ?? ($plan ? $plan->featureCodes() : []);

        $license = License::create([
            'product_id' => $product->id,
            'plan_id' => $plan?->id,
            'customer_id' => $data['customer_id'] ?? null,
            'key' => KeyGenerator::forProduct($product),
            'status' => $data['status'] ?? 'active',
            'max_activations' => $data['max_activations']
                ?? $plan?->max_activations
                ?? $product->default_max_activations,
            'expires_at' => $expires,
            'entitlements' => array_values($entitlements),
            'customer_name' => $data['customer_name'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        LicenseSigner::apply($license);

        return $license;
    }

    /** Re-sign after entitlement/term/status changes. */
    public static function resign(License $license): void
    {
        LicenseSigner::apply($license);
    }
}
