<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SettlementController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Settlement::class);

        $vendor = $request->user()->vendor;

        $settlements = Settlement::query()
            ->where('vendor_id', $vendor->id)
            ->withCount('lines')
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->paginate(15);

        return view('vendor.settlements.index', compact('settlements'));
    }

    public function show(Settlement $settlement): View
    {
        Gate::authorize('view', $settlement);

        $settlement->load(['lines.vendorOrder.order', 'finalizer']);

        return view('vendor.settlements.show', compact('settlement'));
    }
}
