<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $vendor = $request->user()->vendor;
        $this->authorize('view', $vendor);

        return view('vendor.dashboard', [
            'user' => $request->user(),
            'vendor' => $vendor,
            'dailyRevenue' => $this->dailyRevenue($vendor->id),
            'topProducts' => $this->topProducts($vendor->id),
        ]);
    }

    private function dailyRevenue(int $vendorId): array
    {
        $start = today()->subDays(6);
        $end = $start->copy()->addDays(7);

        $totals = DB::table('vendor_orders')
            ->where('vendor_id', $vendorId)
            ->where('status', OrderStatus::COMPLETED->value)
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $end)
            ->selectRaw('DATE(completed_at) as day, SUM(subtotal) as gross, SUM(commission_amount) as commission')
            ->groupByRaw('DATE(completed_at)')
            ->get()
            ->keyBy('day');

        $revenue = [];
        for ($day = $start; $day->lt($end); $day->addDay()) {
            $date = $day->toDateString();
            $total = $totals->get($date);
            $revenue[] = [
                'date' => $date,
                'net' => max(0, (int) ($total->gross ?? 0) - (int) ($total->commission ?? 0)),
            ];
        }

        return $revenue;
    }

    private function topProducts(int $vendorId): array
    {
        return DB::table('order_items')
            ->join('vendor_orders', 'vendor_orders.id', '=', 'order_items.vendor_order_id')
            ->join('skus', 'skus.id', '=', 'order_items.sku_id')
            ->join('products', 'products.id', '=', 'skus.product_id')
            ->where('vendor_orders.vendor_id', $vendorId)
            ->where('vendor_orders.status', OrderStatus::COMPLETED->value)
            ->where('products.vendor_id', $vendorId)
            ->select('products.id as product_id', 'products.name')
            ->selectRaw('SUM(order_items.qty) as qty_sold, SUM(order_items.line_total) as revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('qty_sold')
            ->orderByDesc('revenue')
            ->orderBy('products.id')
            ->limit(5)
            ->get()
            ->map(fn (object $product): array => [
                'product_id' => (int) $product->product_id,
                'name' => $product->name,
                'qty_sold' => (int) $product->qty_sold,
                'revenue' => (int) $product->revenue,
            ])
            ->all();
    }
}
