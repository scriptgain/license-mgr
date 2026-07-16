<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        return Location::query()
            ->withCount('licenseServers')
            ->when($request->boolean('is_default'), fn ($q) => $q->where('is_default', true))
            ->latest()
            ->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $this->validateLocation($request);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        return response()->json(Location::create($data), 201);
    }

    public function show(Location $location)
    {
        return $location->load('licenseServers:id,location_id,name');
    }

    public function update(Request $request, Location $location)
    {
        $location->update($this->validateLocation($request, updating: true));

        return $location;
    }

    public function destroy(Location $location)
    {
        $location->delete();

        return response()->noContent();
    }

    private function validateLocation(Request $request, bool $updating = false): array
    {
        $req = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$req, 'string', 'max:120'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:140',
                Rule::unique('locations', 'slug')->ignore($updating ? $request->route('location') : null),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }
}
