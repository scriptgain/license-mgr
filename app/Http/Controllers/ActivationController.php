<?php

namespace App\Http\Controllers;

use App\Models\Activation;

class ActivationController extends Controller
{
    public function index()
    {
        $activations = Activation::visibleTo(auth()->user())
            ->with('license.product')->latest('last_seen_at')->paginate(50);

        return view('activations.index', compact('activations'));
    }

    public function destroy(Activation $activation)
    {
        abort_unless($activation->isVisibleTo(auth()->user()), 403);
        $activation->delete();

        return back()->with('status', 'Activation released.');
    }
}
