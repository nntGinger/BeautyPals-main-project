<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Services\BatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Batch::class);
        $batches = Batch::query()
            ->whereHas('sku.product', fn ($query) => $query->where('vendor_id', $request->user()->vendor->id))
            ->with('sku.product')->orderBy('exp_date')->orderBy('id')->paginate(15);

        return view('vendor.inventory.index', compact('batches'));
    }

    public function show(Batch $batch): View
    {
        $this->authorize('view', $batch);
        $batch->load('sku.product');
        $movements = $batch->stockMovements()->with('creator')->orderByDesc('id')->paginate(15);

        return view('vendor.inventory.show', compact('batch', 'movements'));
    }

    public function adjust(Request $request, Batch $batch, BatchService $service): RedirectResponse
    {
        $this->authorize('adjust', $batch);
        $validated = $request->validate([
            'delta' => ['required', 'integer', 'between:-2147483648,2147483647', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do điều chỉnh tồn kho.',
            'delta.not_in' => 'Số lượng điều chỉnh phải khác 0.',
        ]);
        $service->adjust($batch, (int) $validated['delta'], $validated['reason'], $request->user()->id);

        return to_route('vendor.inventory.show', $batch)->with('success', 'Đã điều chỉnh tồn kho và lưu lịch sử.');
    }
}
