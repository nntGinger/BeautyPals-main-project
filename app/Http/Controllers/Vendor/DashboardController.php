<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\VendorOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $vendor = $user->vendor;

        $completedStats = VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as completed_count, COALESCE(SUM(total), 0) as gross_revenue, COALESCE(SUM(commission_amount), 0) as total_commission')
            ->first();

        $grossRevenue = (int) ($completedStats->gross_revenue ?? 0);
        $totalCommission = (int) ($completedStats->total_commission ?? 0);
        $netRevenue = $grossRevenue - $totalCommission;
        $completedOrdersCount = (int) ($completedStats->completed_count ?? 0);

        $pendingOrdersCount = VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->count();

        $productsCount = Product::query()
            ->where('vendor_id', $vendor->id)
            ->count();

        $expiringBatchesCount = Batch::query()
            ->whereHas('sku.product', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->where('status', 'active')
            ->whereDate('exp_date', '>=', now()->toDateString())
            ->whereDate('exp_date', '<=', now()->addDays(60)->toDateString())
            ->count();

        // Daily revenue (S4-09)
        $dailyRevenue = VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->selectRaw('DATE(completed_at) as completed_date, COUNT(*) as orders_count, SUM(total) as gross, SUM(total - commission_amount) as net')
            ->groupByRaw('DATE(completed_at)')
            ->orderByDesc('completed_date')
            ->limit(14)
            ->get();

        // Top products by quantity sold (S4-09)
        $topProducts = OrderItem::query()
            ->whereHas('vendorOrder', fn ($q) => $q->where('vendor_id', $vendor->id)->where('status', 'completed'))
            ->selectRaw('product_name, sku_code, SUM(qty) as total_qty, SUM(line_total) as total_revenue')
            ->groupBy('product_name', 'sku_code')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        $recentOrders = VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->with('order.customer')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return view('vendor.dashboard', compact(
            'user',
            'vendor',
            'grossRevenue',
            'totalCommission',
            'netRevenue',
            'completedOrdersCount',
            'pendingOrdersCount',
            'productsCount',
            'expiringBatchesCount',
            'dailyRevenue',
            'topProducts',
            'recentOrders',
        ));
    }
}
