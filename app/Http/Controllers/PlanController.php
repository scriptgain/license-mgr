<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::with('product')->withCount('licenses')->latest()->get();

        return view('plans.index', compact('plans'));
    }

    public function create(Request $request)
    {
        return view('plans.create', [
            'products' => Product::with('features')->orderBy('name')->get(),
            'selectedProduct' => $request->query('product'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']) . '-' . Str::lower(Str::random(4));
        $features = $data['features'] ?? [];
        unset($data['features']);

        $plan = Plan::create($data);
        $this->syncFeatures($plan, $features);

        return redirect()->route('plans.show', $plan)->with('status', "Plan \"{$plan->name}\" created.");
    }

    public function show(Plan $plan)
    {
        $plan->load('product.features', 'features');

        return view('plans.show', compact('plan'));
    }

    public function edit(Plan $plan)
    {
        $plan->load('features');

        return view('plans.edit', [
            'plan' => $plan,
            'products' => Product::with('features')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $this->validated($request);
        $features = $data['features'] ?? [];
        unset($data['features']);

        $plan->update($data);
        $this->syncFeatures($plan, $features);

        return redirect()->route('plans.show', $plan)->with('status', "Plan \"{$plan->name}\" updated.");
    }

    public function destroy(Plan $plan)
    {
        $name = $plan->name;
        $plan->delete();

        return redirect()->route('plans.index')->with('status', "Plan \"{$name}\" deleted.");
    }

    /** Keep only feature ids that belong to the plan's product. */
    private function syncFeatures(Plan $plan, array $featureIds): void
    {
        $allowed = $plan->product->features()->pluck('id');
        $plan->features()->sync(collect($featureIds)->map('intval')->intersect($allowed)->all());
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
            'interval' => ['required', 'in:' . implode(',', array_keys(Plan::INTERVALS))],
            'max_activations' => ['required', 'integer', 'min:0'],
            'expiry_days' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['integer'],
        ]);
    }
}
