<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Platform dashboard metrics (S4-02, S4-10).
     *
     * GMV definition: Total value of completed VendorOrders (SUM(vendor_orders.total) WHERE status = 'completed').
     * Platform Revenue definition: Total commission from completed VendorOrders (SUM(vendor_orders.commission_amount) WHERE status = 'completed').
     */
    public function __invoke(): View
    {
        $completedSummary = VendorOrder::query()
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as completed_count, COALESCE(SUM(total), 0) as gmv, COALESCE(SUM(commission_amount), 0) as platform_revenue')
            ->first();

        $gmv = (int) ($completedSummary->gmv ?? 0);
        $platformRevenue = (int) ($completedSummary->platform_revenue ?? 0);
        $completedVendorOrdersCount = (int) ($completedSummary->completed_count ?? 0);

        $totalOrders = Order::query()->count();
        $totalVendorOrders = VendorOrder::query()->count();
        $userCount = User::query()->count();
        $vendorCount = Vendor::query()->count();
        $pendingVendorsCount = Vendor::query()->where('status', 'pending')->count();
        $pendingVendors = $pendingVendorsCount;
        $pendingProductsCount = Product::query()->where('status', 'pending')->count();

        $vendorKpis = Vendor::query()
            ->withCount([
                'vendorOrders as total_orders_count',
                'vendorOrders as completed_orders_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->withSum(['vendorOrders as completed_gmv' => fn ($q) => $q->where('status', 'completed')], 'total')
            ->withSum(['vendorOrders as completed_commission' => fn ($q) => $q->where('status', 'completed')], 'commission_amount')
            ->orderByDesc('completed_gmv')
            ->limit(10)
            ->get();

        $recentVendorOrders = VendorOrder::query()
            ->with(['order.customer', 'vendor'])
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact(
            'gmv',
            'platformRevenue',
            'completedVendorOrdersCount',
            'totalOrders',
            'totalVendorOrders',
            'userCount',
            'vendorCount',
            'pendingVendors',
            'pendingVendorsCount',
            'pendingProductsCount',
            'vendorKpis',
            'recentVendorOrders',
        ));
    }
}
