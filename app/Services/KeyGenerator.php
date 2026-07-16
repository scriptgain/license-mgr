<?php

namespace App\Services;

use App\Models\License;
use App\Models\Product;

class KeyGenerator
{
    // No ambiguous characters (0/O, 1/I/L) for human-readable keys.
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** A unique license key for a product: PREFIX-XXXX-XXXX-XXXX-XXXX. */
    public static function forProduct(?Product $product = null): string
    {
        $prefix = $product && $product->key_prefix ? strtoupper($product->key_prefix) . '-' : '';

        do {
            $key = $prefix . implode('-', [self::block(), self::block(), self::block(), self::block()]);
        } while (License::withTrashed()->where('key', $key)->exists());

        return $key;
    }

    private static function block(): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 4; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
