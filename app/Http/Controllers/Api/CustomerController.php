<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        return Customer::visibleTo($request->user())
            ->withCount('licenses')
            ->latest()
            ->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = $this->resolveOwner($request);

        return response()->json(Customer::create($data), 201);
    }

    public function show(Customer $customer)
    {
        abort_unless($customer->isVisibleTo(auth()->user()), 403);

        return $customer->load('licenses:id,customer_id,key,status');
    }

    public function update(Request $request, Customer $customer)
    {
        abort_unless($customer->isVisibleTo($request->user()), 403);

        $data = $this->validated($request, updating: true);

        if ($request->user()->isAdmin() && $request->filled('user_id')) {
            $data['user_id'] = $request->validate([
                'user_id' => ['integer', 'exists:users,id'],
            ])['user_id'];
        } else {
            unset($data['user_id']);
        }

        $customer->update($data);

        return $customer;
    }

    public function destroy(Customer $customer)
    {
        abort_unless($customer->isVisibleTo(auth()->user()), 403);

        $customer->delete();

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
            'name' => [$req, 'string', 'max:160'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'company' => ['sometimes', 'nullable', 'string', 'max:160'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:60'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
    }
}
