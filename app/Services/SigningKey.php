<?php

namespace App\Services;

use RuntimeException;

/**
 * Manages this instance's RSA signing keypair. The private key signs licenses;
 * the public key is published to nodes and embedded in customer apps so they can
 * verify licenses offline. Generated once on first use, stored on local disk.
 */
class SigningKey
{
    private static function dir(): string
    {
        return storage_path('app/license-signing');
    }

    private static function privatePath(): string
    {
        return self::dir() . '/private.pem';
    }

    private static function publicPath(): string
    {
        return self::dir() . '/public.pem';
    }

    /** Ensure a keypair exists, generating a 2048-bit RSA pair on first call. */
    public static function ensure(): void
    {
        if (is_file(self::privatePath()) && is_file(self::publicPath())) {
            return;
        }

        if (! is_dir(self::dir())) {
            mkdir(self::dir(), 0700, true);
        }

        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($res === false) {
            throw new RuntimeException('Unable to generate RSA keypair: ' . openssl_error_string());
        }

        openssl_pkey_export($res, $privatePem);
        $publicPem = openssl_pkey_get_details($res)['key'];

        file_put_contents(self::privatePath(), $privatePem);
        chmod(self::privatePath(), 0600);
        file_put_contents(self::publicPath(), $publicPem);
    }

    public static function privateKey(): string
    {
        self::ensure();

        return (string) file_get_contents(self::privatePath());
    }

    public static function publicKey(): string
    {
        self::ensure();

        return (string) file_get_contents(self::publicPath());
    }

    /** SHA-256 fingerprint of the public key, for display / node pinning. */
    public static function fingerprint(): string
    {
        return strtoupper(chunk_split(substr(hash('sha256', self::publicKey()), 0, 32), 4, ' '));
    }
}
