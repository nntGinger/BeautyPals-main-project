<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\InvalidTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * S3-13: Display the customer's order history.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Order::class);

        $userId = (int) $request->user()->id;

        $orders = Order::query()
            ->where('customer_id', $userId)
            ->with('vendorOrders.vendor')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('customer.orders.index', [
            'orders' => $orders,
        ]);
    }

    /**
     * S3-13: Display an order and its vendor orders.
     */
    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        $order->load([
            'vendorOrders.vendor',
            'vendorOrders.items',
            'vendorOrders.statusHistories' => function (
                HasMany $query
            ): void {
                $query
                    ->orderBy('created_at')
                    ->orderBy('id');
            },
        ]);

        $canCancel = Gate::allows('cancel', $order);

        return view('customer.orders.show', [
            'order' => $order,
            'canCancel' => $canCancel,
        ]);
    }

    /**
     * S3-13: Cancel a customer's pending order.
     *
     * Inventory release and order status transitions
     * are delegated to M4's OrderStatusService.
     */
    public function cancel(
        Request $request,
        Order $order,
        OrderStatusService $orderStatusService
    ): RedirectResponse {
        Gate::authorize('cancel', $order);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $reason = trim((string) ($validated['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'Khách hàng yêu cầu hủy đơn.';
        }

        try {
            $orderStatusService->cancelByCustomer(
                $order,
                $request->user(),
                $reason
            );
        } catch (InvalidTransitionException) {
            return redirect()
                ->route('customer.orders.show', $order)
                ->with('error', 'Đơn hàng không thể hủy vì cửa hàng đã bắt đầu xử lý.');
        }

        return redirect()
            ->route('customer.orders.show', $order)
            ->with('success', 'Đơn hàng đã được hủy thành công.');
    }
}
