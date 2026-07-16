<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FeatureController extends Controller
{
    public function index(Request $request)
    {
        return Feature::query()
            ->with('product:id,name')
            ->when($request->integer('product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->latest()
            ->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $this->validateFeature($request);
        $data['code'] = Str::slug($data['code'] ?: $data['name'], '_');

        return response()->json(Feature::create($data), 201);
    }

    public function show(Feature $feature)
    {
        return $feature->load('product:id,name');
    }

    public function update(Request $request, Feature $feature)
    {
        $data = $this->validateFeature($request, updating: true);
        if (array_key_exists('code', $data) || array_key_exists('name', $data)) {
            $data['code'] = Str::slug(($data['code'] ?? '') ?: ($data['name'] ?? $feature->name), '_');
        }
        $feature->update($data);

        return $feature;
    }

    public function destroy(Feature $feature)
    {
        $feature->delete();

        return response()->noContent();
    }

    private function validateFeature(Request $request, bool $updating = false): array
    {
        $req = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'product_id' => [$req, Rule::exists('products', 'id')],
            'name' => [$req, 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('features', 'code')
                    ->where(fn ($q) => $q->where(
                        'product_id',
                        $request->integer('product_id') ?: optional($request->route('feature'))->product_id
                    ))
                    ->ignore($updating ? $request->route('feature') : null),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
