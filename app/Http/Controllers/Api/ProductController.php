<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        return Product::query()
            ->withCount(['plans', 'features'])
            ->latest()
            ->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        return response()->json(Product::create($data), 201);
    }

    public function show(Product $product)
    {
        return $product->load('features', 'plans');
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validateProduct($request, updating: true));

        return $product;
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->noContent();
    }

    private function validateProduct(Request $request, bool $updating = false): array
    {
        $req = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$req, 'string', 'max:120'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:140',
                Rule::unique('products', 'slug')->ignore($updating ? $request->route('product') : null),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'key_prefix' => ['nullable', 'string', 'max:12', 'alpha_num'],
            'default_max_activations' => [$req, 'integer', 'min:0'],
            'default_expiry_days' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
