<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\License;
use App\Services\LicenseIssuer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LicenseController extends Controller
{
    public function index(Request $request)
    {
        return License::visibleTo($request->user())
            ->with(['product:id,name', 'plan:id,name', 'customer:id,name'])
            ->withCount('activations')
            ->when($request->query('status'), function ($q, $status) {
                if (isset(License::STATUSES[$status])) {
                    $q->where('status', $status);
                }
            })
            ->latest()
            ->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertCustomerAllowed($request, $data['customer_id'] ?? null);

        $license = LicenseIssuer::issue($data);
        AuditLog::record('license', "Issued license {$license->key} (API)");

        return response()->json(
            $license->load('product:id,name', 'plan:id,name', 'customer:id,name'),
            201
        );
    }

    public function show(License $license)
    {
        $this->guard($license);

        return $license->load(['product:id,name', 'plan.features', 'customer', 'activations']);
    }

    public function update(Request $request, License $license)
    {
        $this->guard($license);
        $data = $this->validated($request);
        $this->assertCustomerAllowed($request, $data['customer_id'] ?? null);

        $license->update([
            'plan_id' => $data['plan_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'max_activations' => $data['max_activations'],
            'expires_at' => ! empty($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        LicenseIssuer::resign($license);
        AuditLog::record('license', "Updated license {$license->key} (API)");

        return $license->load('product:id,name', 'plan:id,name', 'customer:id,name');
    }

    public function destroy(License $license)
    {
        $this->guard($license);
        $key = $license->key;
        $license->delete();
        AuditLog::record('license', "Revoked+deleted license {$key} (API)");

        return response()->noContent();
    }

    /** Move to a lifecycle status (active|suspended|revoked|expired) and re-sign. */
    public function setStatus(Request $request, License $license)
    {
        $this->guard($license);
        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(License::STATUSES))],
        ]);
        $license->update(['status' => $data['status']]);
        LicenseIssuer::resign($license);
        AuditLog::record('license', "Set license {$license->key} to {$data['status']} (API)");

        return $license->fresh();
    }

    /** Extend the expiry by N months (default 12), reactivate, and re-sign. */
    public function renew(Request $request, License $license)
    {
        $this->guard($license);
        $months = (int) ($request->validate(['months' => ['nullable', 'integer', 'min:1', 'max:120']])['months'] ?? 0) ?: 12;
        $base = $license->expires_at && $license->expires_at->isFuture() ? $license->expires_at : now();
        $license->update(['status' => 'active', 'expires_at' => $base->copy()->addMonths($months)]);
        LicenseIssuer::resign($license);
        AuditLog::record('license', "Renewed license {$license->key} +{$months}mo (API)");

        return $license->fresh();
    }

    /** The signed license file: payload + signature + public key. */
    public function download(License $license)
    {
        $this->guard($license);
        $license->load('product', 'plan');

        return response()->json([
            'license' => $license->canonicalPayload(),
            'signature' => $license->signature,
            'algorithm' => 'RSA-SHA256',
            'public_key' => \App\Services\SigningKey::publicKey(),
            'issued_by' => config('brand.name'),
        ]);
    }

    public function restore(Request $request, $id)
    {
        $license = License::onlyTrashed()->findOrFail($id);
        $this->guard($license);
        $license->restore();
        AuditLog::record('license', "Restored license {$license->key} (API)");

        return $license->fresh();
    }

    /** Owner or admin only; licenses inherit their customer's owner. */
    private function guard(License $license): void
    {
        abort_unless($license->isVisibleTo(auth()->user()), 403);
    }

    /**
     * A non-admin may only attach a license to a customer they own. Admins may
     * attach to any customer (or none).
     */
    private function assertCustomerAllowed(Request $request, ?int $customerId): void
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return;
        }
        abort_if(! $customerId, 422, 'A customer you own is required to issue a license.');
        $customer = Customer::find($customerId);
        abort_unless($customer && $customer->isVisibleTo($user), 403, 'You do not own that customer.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'max_activations' => ['required', 'integer', 'min:0'],
            'expires_at' => ['nullable', 'date'],
            'customer_name' => ['nullable', 'string', 'max:160'],
            'customer_email' => ['nullable', 'email'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
