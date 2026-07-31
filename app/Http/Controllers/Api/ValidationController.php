<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activation;
use App\Models\License;
use App\Services\LicenseSigner;
use App\Services\SigningKey;
use Illuminate\Http\Request;

/**
 * Public license validation API. Client apps (and license-server nodes) call
 * this with a key to check validity and receive the signed license payload for
 * offline re-verification. Base: /api/v1
 */
class ValidationController extends Controller
{
    /** POST /api/v1/validate  { key, fingerprint?, hostname? } */
    public function validateKey(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'fingerprint' => ['nullable', 'string', 'max:190'],
            'hostname' => ['nullable', 'string', 'max:190'],
            // Optional single use challenge. Signed back into the payload so a
            // caller can prove the answer was minted for its own request rather
            // than replayed from a capture.
            'nonce' => ['nullable', 'string', 'max:128'],
        ]);

        $nonce = $data['nonce'] ?? null;

        $license = License::with(['product', 'plan'])->where('key', $data['key'])->first();

        if (! $license) {
            return response()->json($this->signedNegative($data['key'], 'not_found', $nonce), 404);
        }

        if (! $license->isValid()) {
            // Signed, so a client can trust a refusal as much as an approval. An
            // unsigned negative can be forged by anything on the wire, which turns
            // a proxy into a kill switch for a paying customer.
            $payload = $license->leasedPayload($nonce) + ['valid' => false, 'reason' => $license->effectiveStatus()];

            return response()->json([
                'valid' => false,
                'reason' => $license->effectiveStatus(),
                'license' => $payload,
                'signature' => LicenseSigner::signPayload($payload),
                'algorithm' => 'RSA-SHA256',
            ], 200);
        }

        // Optional activation tracking + seat enforcement.
        if (! empty($data['fingerprint'])) {
            $seat = $this->registerActivation($license, $data);
            if ($seat === 'over_limit') {
                return response()->json(
                    $this->signedNegative($license->key, 'activation_limit', $nonce)
                    + ['max_activations' => $license->max_activations], 200);
            }
        }

        // Signed per response, not the stored static signature: the payload now
        // carries issued_at and a lease, so a cached copy stops being current on
        // its own and a revocation cannot be outrun by simply never calling again.
        $payload = $license->leasedPayload($nonce);

        return response()->json([
            'valid' => true,
            'license' => $payload,
            'signature' => LicenseSigner::signPayload($payload),
            'algorithm' => 'RSA-SHA256',
            'lease_expires_at' => $payload['offline_expires_at'],
        ]);
    }

    /** A signed "no", so a client can tell a real refusal from a hostile proxy. */
    private function signedNegative(string $key, string $reason, ?string $nonce): array
    {
        $payload = [
            'key' => $key,
            'valid' => false,
            'reason' => $reason,
            'issued_at' => now()->toIso8601String(),
        ];
        if ($nonce !== null && $nonce !== '') {
            $payload['nonce'] = $nonce;
        }

        return [
            'valid' => false,
            'reason' => $reason,
            'license' => $payload,
            'signature' => LicenseSigner::signPayload($payload),
            'algorithm' => 'RSA-SHA256',
        ];
    }

    /** GET /api/v1/public-key — clients pin this to verify signatures offline. */
    public function publicKey()
    {
        return response(SigningKey::publicKey(), 200, ['Content-Type' => 'application/x-pem-file']);
    }

    private function registerActivation(License $license, array $data): string
    {
        $existing = $license->activations()->where('fingerprint', $data['fingerprint'])->first();

        if (! $existing
            && $license->max_activations > 0
            && $license->activations()->count() >= $license->max_activations) {
            return 'over_limit';
        }

        Activation::updateOrCreate(
            ['license_id' => $license->id, 'fingerprint' => $data['fingerprint']],
            [
                'hostname' => $data['hostname'] ?? null,
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
                'last_seen_at' => now(),
            ]
        );

        return 'ok';
    }
}
