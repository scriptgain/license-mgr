<?php

namespace App\Services;

use App\Models\License;

/**
 * Signs and verifies license payloads with the instance RSA keypair (SHA-256).
 * The signature travels with the license to every node and client, so a license
 * can be validated offline against the published public key.
 */
class LicenseSigner
{
    /** Deterministic JSON so the signed bytes are reproducible on any host. */
    public static function canonicalJson(array $payload): string
    {
        ksort($payload);

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Sign a license's canonical payload; returns base64 signature. */
    public static function sign(License $license): string
    {
        $data = self::canonicalJson($license->canonicalPayload());
        openssl_sign($data, $signature, SigningKey::privateKey(), OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    /** Sign and persist onto the model. */
    public static function apply(License $license): void
    {
        $license->signature = self::sign($license);
        $license->signed_at = now();
        $license->saveQuietly();
    }

    /** Verify a payload+signature against a public key (used by nodes/clients). */
    public static function verify(array $payload, string $base64Signature, ?string $publicKey = null): bool
    {
        $data = self::canonicalJson($payload);

        return openssl_verify(
            $data,
            base64_decode($base64Signature),
            $publicKey ?? SigningKey::publicKey(),
            OPENSSL_ALGO_SHA256
        ) === 1;
    }
}
