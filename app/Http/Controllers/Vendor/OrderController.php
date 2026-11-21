<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidTransitionException;
use App\Http\Controllers\Controller;
use App\Models\VendorOrder;
use App\Services\OrderStateMachine;
use App\Services\OrderStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', VendorOrder::class);
        $validated = $request->validate(['status' => ['nullable', Rule::enum(OrderStatus::class)]]);
        $status = $validated['status'] ?? null;
        $orders = $request->user()->vendor->vendorOrders()
            ->with('order')->withCount('items')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')->paginate(15)->withQueryString();

        return view('vendor.orders.index', ['orders' => $orders, 'status' => $status, 'statuses' => OrderStatus::cases()]);
    }

    public function show(VendorOrder $vendorOrder, OrderStateMachine $stateMachine): View
    {
        $this->authorize('view', $vendorOrder);
        $vendorOrder->load(['order', 'items.allocations.batch', 'statusHistory' => fn ($query) => $query->orderBy('id'), 'statusHistory.changer']);

        return view('vendor.orders.show', [
            'vendorOrder' => $vendorOrder,
            'transitions' => $stateMachine->allowedTransitionsFor($vendorOrder->status),
        ]);
    }

    public function transition(Request $request, VendorOrder $vendorOrder, OrderStatusService $service): RedirectResponse
    {
        $this->authorize('updateStatus', $vendorOrder);
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:255'],
        ], ['reason.required_if' => 'Vui lòng nhập lý do từ chối đơn hàng.']);

        try {
            $service->transition($vendorOrder, $validated['status'], $request->user(), $validated['reason'] ?? null);
        } catch (InvalidTransitionException) {
            throw ValidationException::withMessages(['status' => 'Không thể chuyển sang trạng thái này. Đơn có thể đã được xử lý, vui lòng tải lại trang.']);
        }

        return to_route('vendor.orders.show', $vendorOrder)->with('success', 'Đã cập nhật trạng thái đơn hàng.');
    }
}
