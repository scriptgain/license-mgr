<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::withCount(['plans', 'licenses', 'features'])->latest()->get();

        return view('products.index', compact('products'));
    }

    public function create()
    {
        return view('products.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']) . '-' . Str::lower(Str::random(4));
        $product = Product::create($data);

        return redirect()->route('products.show', $product)->with('status', "Product \"{$product->name}\" created.");
    }

    public function show(Product $product)
    {
        $product->load(['features' => fn ($q) => $q->orderBy('name'), 'plans.features']);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request));

        return redirect()->route('products.show', $product)->with('status', "Product \"{$product->name}\" updated.");
    }

    public function destroy(Product $product)
    {
        $name = $product->name;
        $product->delete();

        return redirect()->route('products.index')->with('status', "Product \"{$name}\" deleted.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'key_prefix' => ['nullable', 'string', 'max:12', 'alpha_num'],
            'default_max_activations' => ['required', 'integer', 'min:0'],
            'default_expiry_days' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
