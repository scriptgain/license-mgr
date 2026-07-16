<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        return Plan::query()
            ->with('product:id,name')
            ->when($request->query('product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->latest()
            ->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']) . '-' . Str::lower(Str::random(4));

        return response()->json(Plan::create($data), 201);
    }

    public function show(Plan $plan)
    {
        return $plan->load('product:id,name', 'features');
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validated($request, updating: true));

        return $plan;
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();

        return response()->noContent();
    }

    private function validated(Request $request, bool $updating = false): array
    {
        $req = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'product_id' => [$req, 'integer', 'exists:products,id'],
            'name' => [$req, 'string', 'max:120'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:160'],
            'price' => [$req, 'numeric', 'min:0'],
            'interval' => [$req, Rule::in(array_keys(Plan::INTERVALS))],
            'max_activations' => [$req, 'integer', 'min:0'],
            'expiry_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);
    }
}
