<?php

namespace App\Http\Controllers\Vendor;

use App\Exceptions\InventoryException;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Sku;
use App\Services\Inventory\BatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $vendor = $request->user()->vendor;
        $today = now()->toDateString();
        $soonThreshold = now()->addDays(60)->toDateString();

        $skus = Sku::query()
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->with(['product', 'batches' => fn ($q) => $q->orderBy('exp_date', 'asc')->orderBy('id', 'asc')])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (Sku $sku) use ($today): Sku {
                $activeUnexpired = $sku->batches->filter(
                    fn (Batch $b) => $b->status === 'active' && $b->exp_date->toDateString() >= $today,
                );

                $onHand = (int) $activeUnexpired->sum('qty_on_hand');
                $reserved = (int) $activeUnexpired->sum('qty_reserved');
                $available = (int) $activeUnexpired->sum(
                    fn (Batch $b) => max(0, (int) $b->qty_on_hand - (int) $b->qty_reserved),
                );

                $sku->setAttribute('calc_on_hand', $onHand);
                $sku->setAttribute('calc_reserved', $reserved);
                $sku->setAttribute('calc_available', $available);

                return $sku;
            });

        $batches = Batch::query()
            ->whereHas('sku.product', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->with('sku.product')
            ->orderBy('exp_date', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(20);

        return view('vendor.inventory.index', compact('skus', 'batches', 'today', 'soonThreshold'));
    }

    public function receive(Request $request, BatchService $batchService): RedirectResponse
    {
        $skuId = (int) $request->input('sku_id');
        $sku = Sku::query()->with('product')->findOrFail($skuId);

        Gate::authorize('receive', [Batch::class, $sku]);

        $batch = $batchService->receive($request->user(), $request->all());

        return redirect()
            ->route('vendor.inventory.index')
            ->with('status', "Đã nhập kho lô {$batch->batch_no} (+{$batch->qty_received} đơn vị).");
    }

    public function adjust(Request $request, Batch $batch, BatchService $batchService): RedirectResponse
    {
        $batch->load('sku.product');
        Gate::authorize('update', $batch);

        try {
            $batchService->adjust(
                $request->user(),
                $batch->id,
                (int) $request->input('delta_qty', 0),
                (string) $request->input('reason', ''),
            );
        } catch (InventoryException $e) {
            throw ValidationException::withMessages([
                'delta_qty' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('vendor.inventory.index')
            ->with('status', "Đã điều chỉnh tồn kho cho lô {$batch->batch_no}.");
    }
}
