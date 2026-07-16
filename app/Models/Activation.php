<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activation extends Model
{
    protected $fillable = [
        'license_id', 'fingerprint', 'hostname', 'ip', 'user_agent', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /** Visibility follows the parent license's customer owner. */
    public function scopeVisibleTo($query, ?User $user)
    {
        if ($user && ! $user->isAdmin()) {
            $query->whereHas('license', fn ($l) => $l->visibleTo($user));
        }

        return $query;
    }

    public function isVisibleTo(?User $user): bool
    {
        return $user && ($user->isAdmin() || ($this->license && $this->license->isVisibleTo($user)));
    }
}
