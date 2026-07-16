<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

/**
 * A license verification node. Every active node replicates the full set of
 * valid licenses from this panel (full replication) and answers /validate
 * requests locally, so verification keeps working even if this panel is offline.
 */
class LicenseServer extends Model
{
    public const STATUSES = [
        'pending' => 'Pending',
        'active' => 'Active',
        'disabled' => 'Disabled',
    ];

    protected $fillable = [
        'location_id', 'user_id', 'name', 'is_local', 'hostname', 'enroll_token', 'status', 'ip',
        'agent_version', 'last_seen_at', 'last_sync_at', 'license_count', 'notes',
    ];

    protected function casts(): array
    {
        return ['is_local' => 'boolean', 'last_seen_at' => 'datetime', 'last_sync_at' => 'datetime'];
    }

    /** Additional (non-built-in) servers — the ones that count against the license. */
    public function scopeAdditional($query)
    {
        return $query->where('is_local', false);
    }

    protected static function booted(): void
    {
        static::creating(function (LicenseServer $s) {
            $s->enroll_token ??= 'lsk_' . Str::lower(Str::random(48));
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Additional users (beyond the owner) who may see this resource. */
    public function assignees(): MorphToMany
    {
        return $this->morphToMany(User::class, 'assignable', 'assignments');
    }

    /** Replace the assignee set with the given user ids. */
    public function syncAssignees(array $userIds): void
    {
        $this->assignees()->sync($userIds);
    }

    /** Admins see all nodes; everyone else sees rows they own or are assigned to. */
    public function scopeVisibleTo($query, ?User $user)
    {
        if ($user && ! $user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('license_servers.user_id', $user->id)
                    ->orWhereHas('assignees', fn ($a) => $a->whereKey($user->id));
            });
        }

        return $query;
    }

    public function isVisibleTo(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isAdmin() || $this->user_id === $user->id) {
            return true;
        }

        return $this->assignees()->whereKey($user->id)->exists();
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(10));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
