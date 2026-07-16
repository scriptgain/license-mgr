<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'key_prefix',
        'default_max_activations', 'default_expiry_days',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }
}
