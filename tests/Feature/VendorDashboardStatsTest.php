<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sku;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendorDashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_revenue_includes_seven_days_and_only_uses_the_signed_in_vendors_completed_orders(): void
    {
        $this->travelTo(now()->setDate(2026, 5, 10)->setTime(12, 0));
        $vendor = Vendor::factory()->approved()->create();
        $otherVendor = Vendor::factory()->approved()->create();

        $this->createVendorOrder($vendor, '2026-05-04 00:00:00', 60000, 10000);
        $this->createVendorOrder($vendor, '2026-05-06 09:00:00', 10000, 12000);
        $this->createVendorOrder($vendor, '2026-05-10 09:00:00', 100000, 20000);
        $this->createVendorOrder($vendor, '2026-05-10 23:59:59', 50000, 60000);
        $this->createVendorOrder($vendor, '2026-05-03 23:59:59', 90000, 0);
        $this->createVendorOrder($vendor, '2026-05-11 00:00:00', 80000, 0);
        $this->createVendorOrder($vendor, '2026-05-07 10:00:00', 70000, 0, OrderStatus::DELIVERED);
        $this->createVendorOrder($otherVendor, '2026-05-10 11:00:00', 200000, 20000);

        $this->actingAs($vendor->owner)
            ->get(route('vendor.dashboard', ['vendor_id' => $otherVendor->id]))
            ->assertOk()
            ->assertViewHas('vendor', fn (Vendor $actual): bool => $actual->is($vendor))
            ->assertViewHas('dailyRevenue', [
                ['date' => '2026-05-04', 'net' => 50000],
                ['date' => '2026-05-05', 'net' => 0],
                ['date' => '2026-05-06', 'net' => 0],
                ['date' => '2026-05-07', 'net' => 0],
                ['date' => '2026-05-08', 'net' => 0],
                ['date' => '2026-05-09', 'net' => 0],
                ['date' => '2026-05-10', 'net' => 70000],
            ]);
    }

    public function test_top_products_aggregates_skus_and_ranks_the_vendors_all_time_completed_sales(): void
    {
        $this->travelTo(now()->setDate(2026, 5, 10)->setTime(12, 0));
        $vendor = Vendor::factory()->approved()->create();
        $otherVendor = Vendor::factory()->approved()->create();
        [$first, $second, $third, $fourth, $fifth, $sixth] = Product::factory()
            ->count(6)
            ->create(['vendor_id' => $vendor->id])
            ->all();

        $completed = $this->createVendorOrder($vendor, '2026-04-01', 11800);
        $this->createOrderItem($completed, $first, 3, 900);
        $this->createOrderItem($completed, $first, 4, 1600);
        $this->createOrderItem($completed, $second, 8, 800);
        $this->createOrderItem($completed, $third, 7, 3000);
        $this->createOrderItem($completed, $fifth, 5, 2000);
        $this->createOrderItem($completed, $fourth, 5, 2000);
        $this->createOrderItem($completed, $sixth, 5, 1500);

        $pending = $this->createVendorOrder($vendor, '2026-05-10', 99900, 0, OrderStatus::PENDING);
        $this->createOrderItem($pending, $sixth, 999, 99900);
        $otherOrder = $this->createVendorOrder($otherVendor, '2026-05-10', 100000);
        $otherProduct = Product::factory()->create(['vendor_id' => $otherVendor->id]);
        $this->createOrderItem($otherOrder, $otherProduct, 1000, 100000);

        $this->actingAs($vendor->owner)
            ->get(route('vendor.dashboard', ['vendor_id' => $otherVendor->id]))
            ->assertOk()
            ->assertViewHas('topProducts', [
                ['product_id' => $second->id, 'name' => $second->name, 'qty_sold' => 8, 'revenue' => 800],
                ['product_id' => $third->id, 'name' => $third->name, 'qty_sold' => 7, 'revenue' => 3000],
                ['product_id' => $first->id, 'name' => $first->name, 'qty_sold' => 7, 'revenue' => 2500],
                ['product_id' => $fourth->id, 'name' => $fourth->name, 'qty_sold' => 5, 'revenue' => 2000],
                ['product_id' => $fifth->id, 'name' => $fifth->name, 'qty_sold' => 5, 'revenue' => 2000],
            ]);
    }

    private function createVendorOrder(
        Vendor $vendor,
        string $completedAt,
        int $subtotal,
        int $commission = 0,
        OrderStatus $status = OrderStatus::COMPLETED,
    ): VendorOrder {
        $order = Order::create([
            'customer_id' => User::factory()->customer()->create()->id,
            'order_code' => 'ORD-'.Str::random(12),
            'receiver' => 'Test Customer',
            'phone' => '0900000000',
            'address_text' => 'Test Address',
            'subtotal' => $subtotal,
            'grand_total' => $subtotal,
            'status' => $status->value,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        return $order->vendorOrders()->create([
            'vendor_id' => $vendor->id,
            'vo_code' => 'VO-'.Str::random(12),
            'status' => $status->value,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'commission_amount' => $commission,
            'completed_at' => $completedAt,
        ]);
    }

    private function createOrderItem(VendorOrder $order, Product $product, int $quantity, int $revenue): void
    {
        $sku = Sku::factory()->for($product)->create();

        $order->items()->create([
            'sku_id' => $sku->id,
            'sku_code' => $sku->sku_code,
            'product_name' => $product->name,
            'unit_price' => intdiv($revenue, $quantity),
            'qty' => $quantity,
            'line_total' => $revenue,
            'commission_rate' => 0,
            'commission_amount' => 0,
        ]);
    }
}
