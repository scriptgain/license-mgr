<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\LicenseServer;
use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use App\Services\SigningKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LicenseServerController extends Controller
{
    public function index()
    {
        $servers = LicenseServer::visibleTo(auth()->user())
            ->with('location', 'owner:id,name')->orderByDesc('is_local')->latest()->get();
        $replicable = License::replicable()->count();

        return view('servers.index', [
            'servers' => $servers,
            'replicable' => $replicable,
            'limit' => $this->additionalLimit(),
            'used' => $this->additionalUsed(),
            'canAdd' => $this->canAddAdditional(),
        ]);
    }

    public function create()
    {
        if (! $this->canAddAdditional()) {
            return $this->limitRedirect();
        }

        return view('servers.create', [
            'locations' => Location::orderByDesc('is_default')->orderBy('name')->get(),
            'defaultLocationId' => optional(Location::default())->id,
            'owners' => $this->assignableOwners(),
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->canAddAdditional()) {
            return $this->limitRedirect();
        }

        $data = $this->validated($request);
        $data['user_id'] = $this->resolveOwner($request);
        unset($data['owner_id']);
        $server = LicenseServer::create($data);
        $this->assignFromRequest($server, $request);

        return redirect()->route('servers.show', $server)
            ->with('status', "License server \"{$server->name}\" registered. Run the installer on the node.");
    }

    public function show(LicenseServer $server)
    {
        $this->guard($server);
        $server->load('location');

        return view('servers.show', [
            'server' => $server,
            'publicKey' => SigningKey::publicKey(),
            'fingerprint' => SigningKey::fingerprint(),
            'installCommand' => $this->installCommand($server),
            'replicable' => License::replicable()->count(),
        ]);
    }

    public function edit(LicenseServer $server)
    {
        $this->guard($server);

        return view('servers.edit', [
            'server' => $server,
            'locations' => Location::orderByDesc('is_default')->orderBy('name')->get(),
            'owners' => $this->assignableOwners(),
        ]);
    }

    public function update(Request $request, LicenseServer $server)
    {
        $this->guard($server);
        // The built-in panel server is not free-form editable.
        $data = $server->is_local
            ? $request->validate(['location_id' => ['nullable', 'integer', 'exists:locations,id'], 'notes' => ['nullable', 'string', 'max:1000']])
            : $this->validated($request);
        // Only admins may reassign ownership.
        if (auth()->user()->isAdmin() && ! $server->is_local) {
            $data['user_id'] = $data['owner_id'] ?? null;
        }
        unset($data['owner_id']);
        $server->update($data);
        $this->assignFromRequest($server, $request);

        return redirect()->route('servers.show', $server)->with('status', 'License server updated.');
    }

    public function regenerate(LicenseServer $server)
    {
        $this->guard($server);
        abort_if($server->is_local, 403, 'The main panel has no enrollment token.');
        $server->update(['enroll_token' => 'lsk_' . Str::lower(Str::random(48))]);

        return back()->with('warning', 'Enrollment token regenerated. Re-run the installer on the node with the new token.');
    }

    public function destroy(LicenseServer $server)
    {
        $this->guard($server);
        abort_if($server->is_local, 403, 'The main panel license server cannot be removed.');
        $name = $server->name;
        $server->delete();

        return redirect()->route('servers.index')->with('status', "License server \"{$name}\" removed.");
    }

    private function guard(LicenseServer $server): void
    {
        abort_unless($server->isVisibleTo(auth()->user()), 403);
    }

    /** Owner to set on store: admins may pick anyone; others own their own nodes. */
    private function resolveOwner(Request $request): int
    {
        $user = $request->user();

        return $user->isAdmin() ? (int) ($request->input('owner_id') ?: $user->id) : $user->id;
    }

    private function assignableOwners()
    {
        return auth()->user()->isAdmin()
            ? User::orderBy('name')->get(['id', 'name', 'email'])
            : collect();
    }

    /** Sync extra assignees from the request. Admins only; others leave the set untouched. */
    private function assignFromRequest($model, Request $request): void
    {
        if (! auth()->user()->isAdmin() || ! method_exists($model, 'syncAssignees')) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('assignees', [])))));
        $model->syncAssignees($ids);
    }

    // --- entitlement gate -------------------------------------------------

    /** Additional (non-local) servers this instance's license permits. */
    private function additionalLimit(): int
    {
        return (int) Setting::get('license_server_limit', 0);
    }

    private function additionalUsed(): int
    {
        return LicenseServer::additional()->count();
    }

    private function canAddAdditional(): bool
    {
        return $this->additionalUsed() < $this->additionalLimit();
    }

    private function limitRedirect()
    {
        $limit = $this->additionalLimit();
        $msg = $limit === 0
            ? 'Additional license servers require license entitlement. The main panel is included; add licenses to run more nodes.'
            : "You've reached your limit of {$limit} additional license server(s). Add licenses to run more nodes.";

        return redirect()->route('servers.index')->with('warning', $msg);
    }

    /** One-line installer for the node (pulls + verifies against this panel). */
    private function installCommand(LicenseServer $server): string
    {
        $base = rtrim(config('app.url'), '/');

        return "curl -fsSL {$base}/install/node.sh | sudo bash -s -- "
            . "--master {$base} --token {$server->enroll_token}";
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'hostname' => ['nullable', 'string', 'max:190'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'status' => ['required', 'in:' . implode(',', array_keys(LicenseServer::STATUSES))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')],
        ]);
    }
}
