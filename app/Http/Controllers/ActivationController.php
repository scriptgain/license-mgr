<?php

namespace App\Http\Controllers;

use App\Models\Activation;
use App\Models\AuditLog;
use Illuminate\Http\Request;

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

    /**
     * Bulk-release selected activations. Only operates on the ids explicitly
     * submitted, and only on activations the current user is allowed to see.
     */
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $user = auth()->user();
        $ids = Activation::visibleTo($user)->whereIn('id', $data['ids'])->pluck('id');

        if ($ids->isEmpty()) {
            return back()->with('warning', 'No matching activations were selected.');
        }

        $count = Activation::visibleTo($user)->whereIn('id', $ids->all())->delete();

        AuditLog::record('activation', "Bulk released {$count} activation".($count === 1 ? '' : 's').'.');

        return back()->with('status', $count.' activation'.($count === 1 ? '' : 's').' released.');
    }
}
