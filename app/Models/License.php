<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class License extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'active' => 'Active',
        'suspended' => 'Suspended',
        'revoked' => 'Revoked',
        'expired' => 'Expired',
    ];

    protected $fillable = [
        'product_id', 'plan_id', 'customer_id', 'key', 'status', 'max_activations',
        'expires_at', 'entitlements', 'customer_name', 'customer_email', 'notes', 'meta',
        'signature', 'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'signed_at' => 'datetime',
            'entitlements' => 'array',
            'meta' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(Activation::class);
    }

    /**
     * A license inherits its owner from its customer. Admins see all licenses;
     * others see only licenses whose customer they own. Licenses with no
     * customer are admin-only.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        if ($user && ! $user->isAdmin()) {
            $query->whereHas('customer', fn ($c) => $c->where('user_id', $user->id));
        }

        return $query;
    }

    public function isVisibleTo(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isAdmin() || ($this->customer && $this->customer->user_id === $user->id);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** The status to act on, resolving date-expiry on read. */
    public function effectiveStatus(): string
    {
        if ($this->status === 'active' && $this->isExpired()) {
            return 'expired';
        }

        return (string) $this->status;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->effectiveStatus()] ?? ucfirst($this->effectiveStatus());
    }

    /** Currently good: active and within term. */
    public function isValid(): bool
    {
        return $this->effectiveStatus() === 'active';
    }

    /** Only the licenses a node should replicate (active + not past term). */
    public function scopeReplicable($query)
    {
        return $query->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    /**
     * Canonical, order-stable payload that gets signed and shipped to nodes.
     * Nodes verify this against the public key to validate offline.
     */
    /**
     * The payload a validation response carries: the canonical claims plus a
     * lease.
     *
     * The stored signature covers canonicalPayload() only, which has no time in
     * it at all. That makes a cached copy immortal: a node or a customer app can
     * verify it offline forever, and a revocation issued afterwards never reaches
     * them (for a perpetual key, expires_at is null, so nothing ever stops it).
     * A lease fixes that without any client having to phone home more often: the
     * blob simply stops verifying as current once offline_expires_at passes, so
     * the worst case exposure after a revocation is one lease period.
     *
     * The field is named offline_expires_at to match ScriptGain's own .lic
     * format, so the two license shapes in this product line stay consistent.
     * Note these payloads are verified by the CUSTOMER's app or a verification
     * node against this instance's SigningKey, not by OfflineLicenseVerifier,
     * which is the opposite direction (it checks this instance's own license
     * against ScriptGain's key).
     */
    public function leasedPayload(?string $nonce = null, ?int $leaseDays = null): array
    {
        $days = $leaseDays ?? (int) config('licensing.lease_days', 14);

        // 'valid' makes the payload self-describing, so a consumer never has to
        // infer entitlement from the HTTP envelope, which is not signed.
        $payload = $this->canonicalPayload() + [
            'valid' => $this->isValid(),
            'issued_at' => now()->toIso8601String(),
            'offline_expires_at' => now()->addDays(max(1, $days))->toIso8601String(),
        ];

        if ($nonce !== null && $nonce !== '') {
            $payload['nonce'] = $nonce;
        }

        return $payload;
    }

    public function canonicalPayload(): array
    {
        return [
            'key' => $this->key,
            'product' => optional($this->product)->slug,
            'plan' => optional($this->plan)->slug,
            'status' => $this->effectiveStatus(),
            'max_activations' => (int) $this->max_activations,
            'expires_at' => optional($this->expires_at)->toIso8601String(),
            'entitlements' => array_values($this->entitlements ?? []),
            'customer' => $this->customer_email ?: $this->customer_name,
        ];
    }
}
