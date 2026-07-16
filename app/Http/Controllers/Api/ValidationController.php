<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activation;
use App\Models\License;
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
        ]);

        $license = License::with(['product', 'plan'])->where('key', $data['key'])->first();

        if (! $license) {
            return response()->json(['valid' => false, 'reason' => 'not_found'], 404);
        }

        if (! $license->isValid()) {
            return response()->json([
                'valid' => false,
                'reason' => $license->effectiveStatus(),
                'license' => $license->canonicalPayload(),
            ], 200);
        }

        // Optional activation tracking + seat enforcement.
        if (! empty($data['fingerprint'])) {
            $seat = $this->registerActivation($license, $data);
            if ($seat === 'over_limit') {
                return response()->json([
                    'valid' => false,
                    'reason' => 'activation_limit',
                    'max_activations' => $license->max_activations,
                ], 200);
            }
        }

        return response()->json([
            'valid' => true,
            'license' => $license->canonicalPayload(),
            'signature' => $license->signature,
            'algorithm' => 'RSA-SHA256',
        ]);
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
