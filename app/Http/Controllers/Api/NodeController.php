<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseServer;
use App\Services\SigningKey;
use Illuminate\Http\Request;

/**
 * License-server (node) replication API. Every active node authenticates with
 * its enrollment token and pulls the FULL set of valid, signed licenses, so it
 * can answer validations locally/offline. Base: /api/node/v1
 */
class NodeController extends Controller
{
    /** Resolve the calling node from its Bearer enrollment token, or 401. */
    private function node(Request $request): LicenseServer
    {
        $token = $request->bearerToken() ?: $request->input('token');
        $node = $token ? LicenseServer::where('enroll_token', $token)->first() : null;

        abort_if(! $node, 401, 'Invalid enrollment token.');
        abort_if($node->status === 'disabled', 403, 'Node disabled.');

        // First successful auth promotes a pending node to active.
        if ($node->status === 'pending') {
            $node->status = 'active';
        }
        $node->last_seen_at = now();
        $node->ip = $request->ip();
        $node->agent_version = substr((string) $request->input('agent_version', $node->agent_version), 0, 40);
        $node->save();

        return $node;
    }

    /** GET /api/node/v1/sync — full replication set of signed, valid licenses. */
    public function sync(Request $request)
    {
        $node = $this->node($request);

        $licenses = License::replicable()->with(['product', 'plan'])->get()->map(fn (License $l) => [
            'license' => $l->canonicalPayload(),
            'signature' => $l->signature,
        ])->all();

        $node->update(['last_sync_at' => now(), 'license_count' => count($licenses)]);

        return response()->json([
            'algorithm' => 'RSA-SHA256',
            'public_key' => SigningKey::publicKey(),
            'count' => count($licenses),
            'synced_at' => now()->toIso8601String(),
            'licenses' => $licenses,
        ]);
    }

    /** POST /api/node/v1/heartbeat — liveness + reported replicated count. */
    public function heartbeat(Request $request)
    {
        $node = $this->node($request);
        if ($request->filled('license_count')) {
            $node->update(['license_count' => (int) $request->input('license_count')]);
        }

        return response()->json(['ok' => true, 'server_time' => now()->toIso8601String()]);
    }
}
