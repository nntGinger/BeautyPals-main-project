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
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $vendor = $request->user()->vendor;
        $status = $request->string('status')->toString();

        $vendorOrders = VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->with(['order.customer', 'items'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('vendor.orders.index', compact('vendorOrders', 'status'));
    }

    public function show(VendorOrder $vendorOrder, OrderStateMachine $stateMachine): View
    {
        Gate::authorize('view', $vendorOrder);

        $vendorOrder->load(['order.customer', 'items.allocations.batch', 'statusHistories.changer']);

        $currentStatus = OrderStatus::from($vendorOrder->status);
        $allowedNext = collect($stateMachine->allowedTransitions($currentStatus))
            ->filter(fn (OrderStatus $s) => ! in_array($s, [OrderStatus::CANCELLED, OrderStatus::COMPLETED], true))
            ->map(fn (OrderStatus $s) => $s->value)
            ->values()
            ->all();

        return view('vendor.orders.show', compact('vendorOrder', 'allowedNext'));
    }

    public function transition(
        Request $request,
        VendorOrder $vendorOrder,
        OrderStatusService $orderStatusService,
    ): RedirectResponse {
        Gate::authorize('update', $vendorOrder);

        $toStatus = trim((string) $request->input('to_status', ''));

        $validated = $request->validate(
            [
                'to_status' => [
                    'required',
                    Rule::in(['confirmed', 'packed', 'shipped', 'delivered', 'rejected']),
                ],
                'cancel_reason' => [
                    Rule::requiredIf($toStatus === 'rejected'),
                    'nullable',
                    'string',
                    'max:255',
                ],
                'tracking_code' => ['nullable', 'string', 'max:60'],
                'note' => ['nullable', 'string', 'max:255'],
            ],
            [
                'to_status.required' => 'Vui lòng chọn trạng thái chuyển tiếp.',
                'to_status.in' => 'Trạng thái chuyển tiếp không hợp lệ đối với cửa hàng.',
                'cancel_reason.required' => 'Vui lòng nhập lý do từ chối đơn hàng.',
            ],
        );

        try {
            $orderStatusService->transition(
                vendorOrderId: $vendorOrder->id,
                to: OrderStatus::from($validated['to_status']),
                changedBy: (int) $request->user()->id,
                note: $validated['note'] ?? null,
                cancelReason: $validated['cancel_reason'] ?? null,
                trackingCode: $validated['tracking_code'] ?? null,
            );
        } catch (InvalidTransitionException $e) {
            throw ValidationException::withMessages([
                'to_status' => 'Chuyển trạng thái không hợp lệ: '.$e->getMessage(),
            ]);
        }

        return redirect()
            ->route('vendor.orders.show', $vendorOrder)
            ->with('status', "Đã cập nhật trạng thái đơn hàng sang {$validated['to_status']}.");
    }
}
