<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidTransitionException;
use App\Http\Controllers\Controller;
use App\Models\VendorOrder;
use App\Services\OrderStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function transition(Request $request, VendorOrder $vendorOrder, OrderStatusService $service): RedirectResponse
    {
        $this->authorize('update', $vendorOrder);

        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:255'],
        ]);

        try {
            $service->transition($vendorOrder, $data['status'], $request->user(), $data['reason'] ?? null);
        } catch (InvalidTransitionException) {
            return back()->withErrors(['status' => 'Không thể chuyển đơn hàng sang trạng thái này. Vui lòng tải lại trang.'])->withInput();
        } catch (ValidationException) {
            return back()->withErrors(['status' => 'Không thể cập nhật tồn kho. Vui lòng kiểm tra phân bổ lô hàng trước khi thử lại.'])->withInput();
        }

        return back()->with('success', 'Đã cập nhật trạng thái đơn hàng.');
    }
}
