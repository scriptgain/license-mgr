<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LicenseServer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LicenseServerController extends Controller
{
    public function index(Request $request)
    {
        return LicenseServer::visibleTo($request->user())
            ->with('location:id,name')
            ->latest()
            ->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = $this->resolveOwner($request);

        return response()->json(LicenseServer::create($data), 201);
    }

    public function show(LicenseServer $licenseServer)
    {
        abort_unless($licenseServer->isVisibleTo(auth()->user()), 403);

        return $licenseServer->load('location:id,name');
    }

    public function update(Request $request, LicenseServer $licenseServer)
    {
        abort_unless($licenseServer->isVisibleTo($request->user()), 403);

        $data = $this->validated($request, updating: true);

        if ($request->user()->isAdmin() && $request->filled('user_id')) {
            $data['user_id'] = $request->validate([
                'user_id' => ['integer', 'exists:users,id'],
            ])['user_id'];
        } else {
            unset($data['user_id']);
        }

        $licenseServer->update($data);

        return $licenseServer;
    }

    public function destroy(LicenseServer $licenseServer)
    {
        abort_unless($licenseServer->isVisibleTo(auth()->user()), 403);

        $licenseServer->delete();

        return response()->noContent();
    }

    /** Admins may assign an explicit owner; everyone else owns what they create. */
    private function resolveOwner(Request $request): int
    {
        if ($request->user()->isAdmin() && $request->filled('user_id')) {
            return (int) $request->validate([
                'user_id' => ['integer', 'exists:users,id'],
            ])['user_id'];
        }

        return $request->user()->id;
    }

    private function validated(Request $request, bool $updating = false): array
    {
        $req = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'location_id' => ['sometimes', 'nullable', 'integer', 'exists:locations,id'],
            'name' => [$req, 'string', 'max:120'],
            'hostname' => ['sometimes', 'nullable', 'string', 'max:190'],
            'status' => [$req, Rule::in(array_keys(LicenseServer::STATUSES))],
            'ip' => ['sometimes', 'nullable', 'string', 'max:45'],
            'is_local' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
    }
}
