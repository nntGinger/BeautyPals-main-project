<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Settlement;
use App\Models\VendorOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $vendor = $user->vendor;

        // Giữ nguyên kiểm tra quyền truy cập Vendor.
        $this->authorize('view', $vendor);

        $completedStatus = OrderStatus::COMPLETED->value;

        // 1. Doanh thu và số kiện hàng đã hoàn tất.
        $orderSummary = VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', $completedStatus)
            ->selectRaw('
                COUNT(*) AS completed_orders_count,
                COALESCE(SUM(subtotal), 0) AS gross_sales,
                COALESCE(SUM(commission_amount), 0) AS commission_total
            ')
            ->first();

        $completedOrdersCount = (int) (
            $orderSummary->completed_orders_count ?? 0
        );

        $grossSales = (int) ($orderSummary->gross_sales ?? 0);
        $commissionTotal = (int) ($orderSummary->commission_total ?? 0);

        $netRevenue = $grossSales - $commissionTotal;

        // 2. Chỉ lấy sản phẩm thuộc các kiện hàng hoàn tất
        // của đúng Vendor đang đăng nhập.
        $completedItems = OrderItem::query()
            ->whereHas('vendorOrder', function ($query) use (
                $vendor,
                $completedStatus
            ) {
                $query
                    ->where('vendor_id', $vendor->id)
                    ->where('status', $completedStatus);
            });

        // 3. Tổng số lượng sản phẩm bán ra.
        $soldItemsCount = (int) (clone $completedItems)
            ->sum('qty');

        // 4. Top 5 sản phẩm theo số lượng bán.
        $topProducts = (clone $completedItems)
            ->select('product_name')
            ->selectRaw('
                SUM(qty) AS sold_quantity,
                SUM(line_total) AS sales_amount
            ')
            ->groupBy('product_name')
            ->orderByDesc('sold_quantity')
            ->limit(5)
            ->get();

        // 5. Tổng số tiền đối soát đang chờ chi trả.
        $pendingPayout = (int) Settlement::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->sum('net_payout');

        // 6. Năm kỳ đối soát mới nhất.
        $recentSettlements = Settlement::query()
            ->where('vendor_id', $vendor->id)
            ->withCount('lines')
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        // 7. Truyền toàn bộ dữ liệu sang Vendor Dashboard.
        return view('vendor.dashboard', compact(
            'user',
            'vendor',
            'netRevenue',
            'completedOrdersCount',
            'soldItemsCount',
            'pendingPayout',
            'topProducts',
            'recentSettlements',
        ));
    }
}
