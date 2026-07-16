<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public const INTERVALS = ['one_time' => 'One-time', 'monthly' => 'Monthly', 'yearly' => 'Yearly'];

    protected $fillable = [
        'product_id', 'name', 'slug', 'price', 'interval', 'max_activations', 'expiry_days',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_feature')->withPivot('value')->withTimestamps();
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    /** Feature codes granted by this plan. */
    public function featureCodes(): array
    {
        return $this->features->pluck('code')->all();
    }
}
