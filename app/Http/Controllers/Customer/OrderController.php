<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\VendorOrder;
use App\Services\OrderStateMachine;
use App\Services\OrderStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Order::class);

        $orders = Order::query()
            ->where('customer_id', $request->user()->id)
            ->with(['vendorOrders.vendor', 'vendorOrders.items'])
            ->orderByDesc('id')
            ->paginate(10);

        return view('customer.orders.index', compact('orders'));
    }

    public function show(Order $order, OrderStateMachine $stateMachine): View
    {
        Gate::authorize('view', $order);

        $order->load([
            'vendorOrders.vendor',
            'vendorOrders.items.allocations.batch',
            'vendorOrders.statusHistories.changer',
            'payments',
        ]);

        foreach ($order->vendorOrders as $vo) {
            $canCancel = $stateMachine->canTransition(
                OrderStatus::from($vo->status),
                OrderStatus::CANCELLED,
            );
            $vo->setAttribute('can_cancel', $canCancel);
        }

        return view('customer.orders.show', compact('order'));
    }

    public function cancel(
        Request $request,
        VendorOrder $vendorOrder,
        OrderStatusService $orderStatusService,
    ): RedirectResponse {
        $vendorOrder->load('order');
        Gate::authorize('cancel', $vendorOrder->order);

        $reason = trim((string) $request->input('cancel_reason', 'Khách hàng yêu cầu hủy đơn'));
        if ($reason === '') {
            $reason = 'Khách hàng yêu cầu hủy đơn';
        }

        try {
            $orderStatusService->transition(
                vendorOrderId: $vendorOrder->id,
                to: OrderStatus::CANCELLED,
                changedBy: (int) $request->user()->id,
                note: $reason,
                cancelReason: $reason,
            );
        } catch (InvalidTransitionException $e) {
            throw ValidationException::withMessages([
                'vendor_order' => 'Không thể hủy đơn hàng ở trạng thái hiện tại: '.$e->getMessage(),
            ]);
        }

        return redirect()
            ->route('customer.orders.show', $vendorOrder->order_id)
            ->with('status', "Đã hủy đơn hàng {$vendorOrder->vo_code} và hoàn trả tồn kho giữ chỗ.");
    }
}
