<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::visibleTo(auth()->user())
            ->with('owner:id,name')->withCount('licenses')->latest()->get();

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create', ['owners' => $this->assignableOwners()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = $this->resolveOwner($request);
        unset($data['owner_id']);

        $customer = Customer::create($data);
        $this->assignFromRequest($customer, $request);

        return redirect()->route('customers.show', $customer)->with('status', "Customer \"{$customer->name}\" created.");
    }

    public function show(Customer $customer)
    {
        $this->guard($customer);
        $customer->load(['licenses' => fn ($q) => $q->with('product')->latest()]);

        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        $this->guard($customer);

        return view('customers.edit', ['customer' => $customer, 'owners' => $this->assignableOwners()]);
    }

    public function update(Request $request, Customer $customer)
    {
        $this->guard($customer);
        $data = $this->validated($request);
        // Only admins may reassign ownership.
        if (auth()->user()->isAdmin()) {
            $data['user_id'] = $data['owner_id'] ?? null;
        }
        unset($data['owner_id']);

        $customer->update($data);
        $this->assignFromRequest($customer, $request);

        return redirect()->route('customers.show', $customer)->with('status', "Customer \"{$customer->name}\" updated.");
    }

    public function destroy(Customer $customer)
    {
        $this->guard($customer);
        $name = $customer->name;
        $customer->delete();

        return redirect()->route('customers.index')->with('status', "Customer \"{$name}\" deleted.");
    }

    private function guard(Customer $customer): void
    {
        abort_unless($customer->isVisibleTo(auth()->user()), 403);
    }

    /** Owner to set on store: admins may pick anyone; others own their own rows. */
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

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'company' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')],
        ]);
    }
}
