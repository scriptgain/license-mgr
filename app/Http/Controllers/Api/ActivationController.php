<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activation;
use Illuminate\Http\Request;

class ActivationController extends Controller
{
    public function index(Request $request)
    {
        return Activation::visibleTo($request->user())
            ->with('license:id,key,customer_id')
            ->when($request->integer('license_id'), fn ($q, $id) => $q->where('license_id', $id))
            ->latest('last_seen_at')
            ->paginate(50);
    }

    public function show(Activation $activation)
    {
        abort_unless($activation->isVisibleTo(auth()->user()), 403);

        return $activation->load('license:id,key,customer_id');
    }

    public function destroy(Activation $activation)
    {
        abort_unless($activation->isVisibleTo(auth()->user()), 403);

        $activation->delete();

        return response()->noContent();
    }
}
