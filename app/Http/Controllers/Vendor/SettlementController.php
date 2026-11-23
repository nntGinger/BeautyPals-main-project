<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettlementController extends Controller
{
    /**
     * S4-11: List settlements belonging to the authenticated approved vendor.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Settlement::class);

        $vendor = $request->user()->vendor;

        $settlements = Settlement::query()
            ->where('vendor_id', $vendor->id)
            ->withCount('lines')
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('vendor.settlements.index', compact('vendor', 'settlements'));
    }

    /**
     * S4-11: View details of a single vendor settlement batch.
     */
    public function show(Settlement $settlement): View
    {
        $this->authorize('view', $settlement);

        $settlement->load(['lines.vendorOrder.order', 'finalizer']);

        return view('vendor.settlements.show', compact('settlement'));
    }
}
