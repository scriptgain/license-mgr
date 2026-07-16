<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FeatureController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $data['code'] = Str::slug($data['code'] ?: $data['name'], '_');

        $product->features()->firstOrCreate(['code' => $data['code']], $data);

        return back()->with('status', "Feature \"{$data['name']}\" added.");
    }

    public function destroy(Product $product, Feature $feature)
    {
        abort_unless($feature->product_id === $product->id, 404);
        $feature->delete();

        return back()->with('status', 'Feature removed.');
    }
}
