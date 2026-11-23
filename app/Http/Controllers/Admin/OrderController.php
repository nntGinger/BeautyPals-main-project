<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * S4-02: Platform-wide vendor order monitoring with filters and pagination.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', VendorOrder::class);

        $validated = $request->validate([
            'vendor_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $vendorId = isset($validated['vendor_id']) ? (int) $validated['vendor_id'] : null;
        $status = $validated['status'] ?? '';
        $from = $validated['from'] ?? ($validated['from_date'] ?? '');
        $to = $validated['to'] ?? ($validated['to_date'] ?? '');

        $vendorOrders = VendorOrder::query()
            ->with(['order.customer', 'vendor'])
            ->withCount('items')
            ->when($vendorId !== null, fn ($query) => $query->where('vendor_id', $vendorId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($from !== '', fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $vendors = Vendor::query()->orderBy('store_name')->get(['id', 'store_name']);
        $statuses = OrderStatus::cases();

        return view('admin.orders.index', compact(
            'vendorOrders',
            'vendors',
            'statuses',
            'vendorId',
            'status',
            'from',
            'to',
        ));
    }

    /**
     * S4-02: Display full details for a single vendor order across items, FEFO batches, and history.
     */
    public function show(VendorOrder $vendorOrder): View
    {
        $this->authorize('view', $vendorOrder);

        $vendorOrder->load([
            'order.customer',
            'order.payments',
            'vendor',
            'items.sku.product',
            'items.allocations.batch',
            'statusHistory' => fn ($query) => $query->orderBy('id'),
            'statusHistory.changer',
        ]);

        return view('admin.orders.show', compact('vendorOrder'));
    }
}

