<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SettlementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Settlement::class);

        $settlements = Settlement::query()
            ->with(['vendor', 'finalizer'])
            ->withCount('lines')
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.settlements.index', compact('settlements'));
    }

    public function show(Settlement $settlement): View
    {
        Gate::authorize('view', $settlement);

        $settlement->load(['vendor', 'finalizer', 'lines.vendorOrder.order']);

        return view('admin.settlements.show', compact('settlement'));
    }

    public function generate(Request $request, SettlementService $settlementService): RedirectResponse
    {
        Gate::authorize('create', Settlement::class);

        $validated = $request->validate(
            [
                'period_start' => ['required', 'date'],
                'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            ],
            [
                'period_start.required' => 'Vui lòng chọn ngày bắt đầu kỳ đối soát.',
                'period_end.required' => 'Vui lòng chọn ngày kết thúc kỳ đối soát.',
                'period_end.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            ],
        );

        $generated = $settlementService->generate(
            $validated['period_start'],
            $validated['period_end'],
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.settlements.index')
            ->with('status', "Đã chốt đối soát kỳ {$validated['period_start']} → {$validated['period_end']} ({$generated->count()} kỳ vendor).");
    }

    public function markPaid(Request $request, Settlement $settlement, SettlementService $settlementService): RedirectResponse
    {
        Gate::authorize('update', $settlement);

        $settlementService->markPaid($settlement->id, (int) $request->user()->id);

        return redirect()
            ->route('admin.settlements.show', $settlement)
            ->with('status', 'Đã đánh dấu kỳ đối soát là đã chi trả (paid).');
    }
}
