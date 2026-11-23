<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
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
     * S4-02: Platform monitoring KPIs while preserving core user/vendor counts.
     */
    public function __invoke(): View
    {
        $this->authorize('viewAny', User::class);

        $excluded = [OrderStatus::CANCELLED->value, OrderStatus::REJECTED->value];

        $orderSummary = VendorOrder::query()
            ->whereNotIn('status', $excluded)
            ->selectRaw('COUNT(*) as active_vendor_orders_count, COALESCE(SUM(subtotal), 0) as gmv, COALESCE(SUM(commission_amount), 0) as platform_revenue')
            ->first();

        $gmv = (int) ($orderSummary->gmv ?? 0);
        $platformRevenue = (int) ($orderSummary->platform_revenue ?? 0);
        $activeVendorOrdersCount = (int) ($orderSummary->active_vendor_orders_count ?? 0);

        $orderCount = Order::query()->count();
        $userCount = User::query()->count();
        $vendorCount = Vendor::query()->count();
        $pendingVendors = Vendor::query()->where('status', 'pending')->count();
        $pendingProductsCount = Product::query()->where('status', 'pending')->count();
        $lockedUsersCount = User::query()->where('status', 'locked')->count();

        $vendorKpis = Vendor::query()
            ->withCount([
                'vendorOrders as total_orders_count',
                'vendorOrders as active_orders_count' => fn ($query) => $query->whereNotIn('status', $excluded),
            ])
            ->withSum(['vendorOrders as active_gmv' => fn ($query) => $query->whereNotIn('status', $excluded)], 'subtotal')
            ->withSum(['vendorOrders as active_commission' => fn ($query) => $query->whereNotIn('status', $excluded)], 'commission_amount')
            ->orderByDesc('active_gmv')
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
            'activeVendorOrdersCount',
            'orderCount',
            'userCount',
            'vendorCount',
            'pendingVendors',
            'pendingProductsCount',
            'lockedUsersCount',
            'vendorKpis',
            'recentVendorOrders',
        ));
    }
}
