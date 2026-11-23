<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use ReflectionMethod;

class SettlementController extends Controller
{
    private const SETTLEMENT_SERVICE = 'App\\Services\\SettlementService';

    /**
     * S4-11: Display settlement batches for platform finance supervision.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Settlement::class);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'pending', 'finalized', 'paid'])],
        ]);

        $status = (string) ($validated['status'] ?? '');

        $settlements = Settlement::query()
            ->with(['vendor', 'finalizer'])
            ->withCount('lines')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $settlementServiceReady = $this->settlementServiceAvailable();

        return view('admin.settlements.index', compact(
            'settlements',
            'status',
            'settlementServiceReady',
        ));
    }

    /**
     * S4-11: Show detailed orders included in a settlement period.
     */
    public function show(Settlement $settlement): View
    {
        $this->authorize('view', $settlement);

        $settlement->load(['vendor', 'finalizer', 'lines.vendorOrder.order']);
        $settlementServiceReady = $this->settlementServiceAvailable();

        return view('admin.settlements.show', compact('settlement', 'settlementServiceReady'));
    }

    /**
     * S4-11: Delegate settlement generation to M4 SettlementService (S4-08) when integrated.
     */
    public function generate(Request $request): RedirectResponse
    {
        $this->authorize('create', Settlement::class);

        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ], [
            'period_start.required' => 'Vui lòng chọn ngày bắt đầu kỳ đối soát.',
            'period_end.required' => 'Vui lòng chọn ngày kết thúc kỳ đối soát.',
            'period_end.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ]);

        if (! $this->settlementServiceAvailable()) {
            return redirect()
                ->route('admin.settlements.index')
                ->withInput()
                ->with('error', 'Dịch vụ đối soát SettlementService (S4-08) chưa được tích hợp trên hệ thống.');
        }

        $service = app(self::SETTLEMENT_SERVICE);
        $method = new ReflectionMethod($service, 'generate');
        $params = $method->getParameters();

        // Adapt seamlessly to either M4 handover signature (?int $vendorId = null) or (?int $finalizedBy = null).
        if (isset($params[2]) && $params[2]->getName() === 'vendorId') {
            $generated = $service->generate($validated['period_start'], $validated['period_end'], null);
        } else {
            $generated = $service->generate(
                $validated['period_start'],
                $validated['period_end'],
                (int) $request->user()->id,
            );
        }

        return redirect()
            ->route('admin.settlements.index')
            ->with('success', "Đã chốt đối soát kỳ {$validated['period_start']} → {$validated['period_end']} ({$generated->count()} kỳ gian hàng).");
    }

    /**
     * S4-11: Mark a settlement period as paid via M4 SettlementService (S4-08).
     */
    public function markPaid(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->authorize('update', $settlement);

        if (! $this->settlementServiceAvailable()) {
            return redirect()
                ->route('admin.settlements.show', $settlement)
                ->with('error', 'Dịch vụ đối soát SettlementService (S4-08) chưa được tích hợp trên hệ thống.');
        }

        $service = app(self::SETTLEMENT_SERVICE);
        $method = new ReflectionMethod($service, 'markPaid');
        $firstParam = $method->getParameters()[0] ?? null;
        $firstType = $firstParam?->getType()?->getName();

        if ($firstType === Settlement::class) {
            $service->markPaid($settlement);
        } else {
            $service->markPaid($settlement->id, (int) $request->user()->id);
        }

        return redirect()
            ->route('admin.settlements.show', $settlement)
            ->with('success', 'Đã đánh dấu kỳ đối soát là đã chi trả.');
    }

    private function settlementServiceAvailable(): bool
    {
        if (app()->bound(self::SETTLEMENT_SERVICE)) {
            return true;
        }

        return is_file(app_path('Services/SettlementService.php'))
            && class_exists(self::SETTLEMENT_SERVICE);
    }
}
