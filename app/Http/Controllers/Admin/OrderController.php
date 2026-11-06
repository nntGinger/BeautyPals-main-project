<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $vendorId = $request->filled('vendor_id') ? (int) $request->input('vendor_id') : null;
        $status = $request->string('status')->toString();
        $fromDate = $request->string('from_date')->toString();
        $toDate = $request->string('to_date')->toString();

        $vendorOrders = VendorOrder::query()
            ->with(['order.customer', 'vendor', 'items'])
            ->when($vendorId !== null && $vendorId > 0, fn ($q) => $q->where('vendor_id', $vendorId))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($fromDate !== '', fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate !== '', fn ($q) => $q->whereDate('created_at', '<=', $toDate))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $vendors = Vendor::query()->orderBy('store_name')->get();

        return view('admin.orders.index', compact('vendorOrders', 'vendors', 'vendorId', 'status', 'fromDate', 'toDate'));
    }

    public function show(VendorOrder $vendorOrder): View
    {
        $vendorOrder->load(['order.customer', 'order.payments', 'vendor', 'items.allocations.batch', 'statusHistories.changer']);

        return view('admin.orders.show', compact('vendorOrder'));
    }
}
