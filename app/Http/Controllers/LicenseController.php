<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Services\LicenseIssuer;
use App\Services\LicenseSigner;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LicenseController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $status = $request->query('status');
        $query = License::visibleTo($user)->with(['product', 'plan', 'customer'])->withCount('activations');

        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($status && isset(License::STATUSES[$status])) {
            $query->where('status', $status);
        }

        $licenses = $query->latest()->paginate(25)->withQueryString();

        $stats = [
            'total' => License::visibleTo($user)->count(),
            'active' => License::visibleTo($user)->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))->count(),
            'revoked' => License::visibleTo($user)->where('status', 'revoked')->count(),
        ];

        return view('licenses.index', compact('licenses', 'status', 'stats'));
    }

    public function create()
    {
        return view('licenses.create', [
            'products' => Product::with('plans')->orderBy('name')->get(),
            'customers' => Customer::visibleTo(auth()->user())->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertCustomerAllowed($request, $data['customer_id'] ?? null);
        $license = LicenseIssuer::issue($data);
        AuditLog::record('license', "Issued license {$license->key}");

        return redirect()->route('licenses.show', $license)->with('status', "License issued: {$license->key}");
    }

    public function show(License $license)
    {
        $this->guard($license);
        $license->load(['product', 'plan.features', 'customer', 'activations']);

        return view('licenses.show', compact('license'));
    }

    public function edit(License $license)
    {
        $this->guard($license);

        return view('licenses.edit', [
            'license' => $license,
            'products' => Product::with('plans')->orderBy('name')->get(),
            'customers' => Customer::visibleTo(auth()->user())->orderBy('name')->get(),
        ]);
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
        AuditLog::record('license', "Updated license {$license->key}");

        return redirect()->route('licenses.show', $license)->with('status', 'License updated.');
    }

    /** Move to a lifecycle status and re-sign. */
    public function setStatus(Request $request, License $license)
    {
        $this->guard($license);
        $data = $request->validate(['status' => ['required', 'in:' . implode(',', array_keys(License::STATUSES))]]);
        $license->update(['status' => $data['status']]);
        LicenseIssuer::resign($license);
        AuditLog::record('license', "Set license {$license->key} to {$data['status']}");

        return back()->with('status', "Status set to {$license->statusLabel()}.");
    }

    public function renew(Request $request, License $license)
    {
        $this->guard($license);
        $months = (int) $request->validate(['months' => ['nullable', 'integer', 'min:1', 'max:120']])['months'] ?: 12;
        $base = $license->expires_at && $license->expires_at->isFuture() ? $license->expires_at : now();
        $license->update(['status' => 'active', 'expires_at' => $base->copy()->addMonths($months)]);
        LicenseIssuer::resign($license);
        AuditLog::record('license', "Renewed license {$license->key} +{$months}mo");

        return back()->with('status', "Renewed for {$months} month(s).");
    }

    /** Download the signed license file (key + payload + signature + public key). */
    public function download(License $license)
    {
        $this->guard($license);
        $license->load('product', 'plan');
        $payload = $license->canonicalPayload();
        $file = [
            'license' => $payload,
            'signature' => $license->signature,
            'algorithm' => 'RSA-SHA256',
            'public_key' => \App\Services\SigningKey::publicKey(),
            'issued_by' => config('brand.name'),
        ];

        return response(json_encode($file, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $license->key . '.license"',
        ]);
    }

    public function destroy(License $license)
    {
        $this->guard($license);
        $key = $license->key;
        $license->delete();
        AuditLog::record('license', "Revoked+deleted license {$key}");

        return redirect()->route('licenses.index')->with('status', "License {$key} deleted.");
    }

    public function restore($id)
    {
        $license = License::onlyTrashed()->findOrFail($id);
        $this->guard($license);
        $license->restore();

        return redirect()->route('licenses.show', $license)->with('status', 'License restored.');
    }

    /** Owner or admin only; a license inherits its customer's owner. */
    private function guard(License $license): void
    {
        abort_unless($license->isVisibleTo(auth()->user()), 403);
    }

    /** A non-admin may only attach a license to a customer they own. */
    private function assertCustomerAllowed(Request $request, ?int $customerId): void
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return;
        }
        abort_if(! $customerId, 422, 'Select one of your customers to issue this license.');
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
