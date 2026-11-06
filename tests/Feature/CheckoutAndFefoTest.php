<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Exceptions\InventoryException;
use App\Models\Address;
use App\Models\Batch;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sku;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\Inventory\InventoryService;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutAndFefoTest extends TestCase
{
    use RefreshDatabase;

    private function seedDefaultCommissionRule(): Category
    {
        CommissionRule::create([
            'category_id' => null,
            'category_key' => 0,
            'rate' => '10.00',
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        return Category::factory()->create(['name' => 'Skincare', 'slug' => 'skincare']);
    }

    public function test_three_vendor_cart_places_one_parent_and_three_vendor_orders_with_idempotency_and_snapshots(): void
    {
        $category = $this->seedDefaultCommissionRule();
        $customer = User::factory()->customer()->create();
        $address = Address::factory()->default()->create([
            'user_id' => $customer->id,
            'receiver' => 'Nguyễn Thu Hà',
            'phone' => '0912345678',
            'line' => '100 Lê Lợi',
            'ward' => 'Phường Bến Thành',
            'district' => 'Quận 1',
            'city' => 'TP. Hồ Chí Minh',
        ]);

        $vendors = Vendor::factory()->count(3)->approved()->create();
        $skus = [];

        foreach ($vendors as $idx => $vendor) {
            $product = Product::factory()->approved()->create([
                'vendor_id' => $vendor->id,
                'category_id' => $category->id,
                'name' => 'Sản phẩm Vendor '.($idx + 1),
            ]);
            $sku = Sku::factory()->create([
                'product_id' => $product->id,
                'sku_code' => 'SKU-V'.($idx + 1),
                'price' => 100000 * ($idx + 1),
                'is_active' => true,
            ]);
            Batch::factory()->create([
                'sku_id' => $sku->id,
                'batch_no' => 'LOT-V'.($idx + 1),
                'qty_received' => 50,
                'qty_on_hand' => 50,
                'qty_reserved' => 0,
                'exp_date' => now()->addMonths(12)->toDateString(),
            ]);
            $skus[] = $sku;
        }

        $cartService = app(CartService::class);
        $cartService->addItem($customer->id, $skus[0]->id, 2); // 200,000
        $cartService->addItem($customer->id, $skus[1]->id, 1); // 200,000
        $cartService->addItem($customer->id, $skus[2]->id, 1); // 300,000

        $idempotencyKey = (string) Str::uuid();
        $checkoutService = app(CheckoutService::class);

        $order = $checkoutService->place($customer->id, $address->id, $idempotencyKey);

        $this->assertCount(3, $order->vendorOrders);
        $this->assertSame(700000, $order->grand_total);
        $this->assertSame(70000, (int) $order->vendorOrders->sum('commission_amount'));
        $this->assertStringContainsString('100 Lê Lợi', $order->address_text);

        // Cart is cleared
        $this->assertEmpty($cartService->getCartGroupedByVendor($customer->id)['vendors']);

        // Submitting same idempotency_key returns the exact same Order without duplicating
        $duplicateCall = $checkoutService->place($customer->id, $address->id, $idempotencyKey);
        $this->assertSame($order->id, $duplicateCall->id);
        $this->assertSame(1, Order::count());

        // Changing SKU price or CommissionRule afterwards does NOT alter existing OrderItems
        $skus[0]->update(['price' => 999000]);
        CommissionRule::create([
            'category_id' => $category->id,
            'category_key' => $category->id,
            'rate' => '25.00',
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $order->refresh();
        $firstItem = $order->vendorOrders->firstWhere('vendor_id', $vendors[0]->id)->items->first();
        $this->assertSame(100000, $firstItem->unit_price);
        $this->assertSame('10.00', (string) $firstItem->commission_rate);
        $this->assertSame(20000, $firstItem->commission_amount);
    }

    public function test_fefo_allocates_100_and_50_from_batches_100_and_200_and_skips_expired(): void
    {
        $category = $this->seedDefaultCommissionRule();
        $customer = User::factory()->customer()->create();
        $address = Address::factory()->default()->create(['user_id' => $customer->id]);

        $vendor = Vendor::factory()->approved()->create();
        $product = Product::factory()->approved()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
        ]);
        $sku = Sku::factory()->create([
            'product_id' => $product->id,
            'sku_code' => 'SKU-FEFO-150',
            'price' => 100000,
            'is_active' => true,
        ]);

        // Expired batch (must be skipped even though it has earliest exp_date)
        $expiredBatch = Batch::factory()->create([
            'sku_id' => $sku->id,
            'batch_no' => 'LOT-EXPIRED',
            'mfg_date' => now()->subMonths(24)->toDateString(),
            'exp_date' => now()->subDays(2)->toDateString(),
            'qty_received' => 500,
            'qty_on_hand' => 500,
            'qty_reserved' => 0,
            'status' => 'active',
        ]);

        // Batch 2 (later expiry: +12 months, 200 units)
        $laterBatch = Batch::factory()->create([
            'sku_id' => $sku->id,
            'batch_no' => 'LOT-LATER-200',
            'mfg_date' => now()->subMonths(2)->toDateString(),
            'exp_date' => now()->addMonths(12)->toDateString(),
            'qty_received' => 200,
            'qty_on_hand' => 200,
            'qty_reserved' => 0,
            'status' => 'active',
        ]);

        // Batch 1 (earlier expiry: +2 months, 100 units)
        $earlierBatch = Batch::factory()->create([
            'sku_id' => $sku->id,
            'batch_no' => 'LOT-EARLIER-100',
            'mfg_date' => now()->subMonths(6)->toDateString(),
            'exp_date' => now()->addMonths(2)->toDateString(),
            'qty_received' => 100,
            'qty_on_hand' => 100,
            'qty_reserved' => 0,
            'status' => 'active',
        ]);

        app(CartService::class)->addItem($customer->id, $sku->id, 150);
        $order = app(CheckoutService::class)->place($customer->id, $address->id, (string) Str::uuid());

        $orderItem = $order->vendorOrders->first()->items->first();
        $allocations = $orderItem->allocations->keyBy('batch_id');

        $this->assertFalse($allocations->has($expiredBatch->id));
        $this->assertSame(100, $allocations[$earlierBatch->id]->qty);
        $this->assertSame(50, $allocations[$laterBatch->id]->qty);

        $this->assertSame(0, $expiredBatch->fresh()->qty_reserved);
        $this->assertSame(100, $earlierBatch->fresh()->qty_reserved);
        $this->assertSame(50, $laterBatch->fresh()->qty_reserved);
    }

    public function test_oversell_when_stock_is_one_allows_only_first_checkout_and_rolls_back_second(): void
    {
        $category = $this->seedDefaultCommissionRule();
        $customer1 = User::factory()->customer()->create();
        $customer2 = User::factory()->customer()->create();
        $addr1 = Address::factory()->default()->create(['user_id' => $customer1->id]);
        $addr2 = Address::factory()->default()->create(['user_id' => $customer2->id]);

        $vendor = Vendor::factory()->approved()->create();
        $product = Product::factory()->approved()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
        ]);
        $sku = Sku::factory()->create([
            'product_id' => $product->id,
            'sku_code' => 'SKU-LAST-ONE',
            'price' => 150000,
            'is_active' => true,
        ]);
        $batch = Batch::factory()->create([
            'sku_id' => $sku->id,
            'batch_no' => 'LOT-ONLY-1',
            'qty_received' => 1,
            'qty_on_hand' => 1,
            'qty_reserved' => 0,
            'exp_date' => now()->addMonths(6)->toDateString(),
        ]);

        $cartService = app(CartService::class);
        // Both customers add the last item to their cart while available = 1
        $cartService->addItem($customer1->id, $sku->id, 1);
        $cartService->addItem($customer2->id, $sku->id, 1);

        $checkoutService = app(CheckoutService::class);
        $order1 = $checkoutService->place($customer1->id, $addr1->id, (string) Str::uuid());
        $this->assertNotNull($order1->id);

        // Second customer's checkout must fail and roll back cleanly
        $this->expectException(InventoryException::class);

        try {
            $checkoutService->place($customer2->id, $addr2->id, (string) Str::uuid());
        } finally {
            $this->assertSame(1, Order::count());
            $this->assertSame(1, $batch->fresh()->qty_reserved);
            $this->assertSame(1, $batch->fresh()->qty_on_hand);
        }
    }

    public function test_cancel_releases_stock_idempotently_and_shipped_commits_stock_idempotently_and_auto_completes_after_7_days(): void
    {
        $category = $this->seedDefaultCommissionRule();
        $customer = User::factory()->customer()->create();
        $address = Address::factory()->default()->create(['user_id' => $customer->id]);

        $vendor1 = Vendor::factory()->approved()->create();
        $vendor2 = Vendor::factory()->approved()->create();

        $prod1 = Product::factory()->approved()->create(['vendor_id' => $vendor1->id, 'category_id' => $category->id]);
        $sku1 = Sku::factory()->create(['product_id' => $prod1->id, 'price' => 200000]);
        $batch1 = Batch::factory()->create(['sku_id' => $sku1->id, 'qty_received' => 20, 'qty_on_hand' => 20, 'qty_reserved' => 0]);

        $prod2 = Product::factory()->approved()->create(['vendor_id' => $vendor2->id, 'category_id' => $category->id]);
        $sku2 = Sku::factory()->create(['product_id' => $prod2->id, 'price' => 300000]);
        $batch2 = Batch::factory()->create(['sku_id' => $sku2->id, 'qty_received' => 30, 'qty_on_hand' => 30, 'qty_reserved' => 0]);

        $cartService = app(CartService::class);
        $cartService->addItem($customer->id, $sku1->id, 5);
        $cartService->addItem($customer->id, $sku2->id, 10);

        $order = app(CheckoutService::class)->place($customer->id, $address->id, (string) Str::uuid());

        $vo1 = $order->vendorOrders->firstWhere('vendor_id', $vendor1->id);
        $vo2 = $order->vendorOrders->firstWhere('vendor_id', $vendor2->id);

        $this->assertSame(5, $batch1->fresh()->qty_reserved);
        $this->assertSame(10, $batch2->fresh()->qty_reserved);

        $statusService = app(OrderStatusService::class);
        $inventoryService = app(InventoryService::class);

        // Cancel VendorOrder 1 -> releases 5 units back to batch1
        $statusService->transition($vo1->id, OrderStatus::CANCELLED, $customer->id, 'Đổi ý', 'Đổi ý');
        $this->assertSame(0, $batch1->fresh()->qty_reserved);
        $this->assertSame(20, $batch1->fresh()->qty_on_hand);

        // Calling release a second time is idempotent
        $inventoryService->releaseForVendorOrder($vo1->id, $customer->id);
        $this->assertSame(0, $batch1->fresh()->qty_reserved);
        $this->assertSame(20, $batch1->fresh()->qty_on_hand);

        // Parent order becomes 'processing' when vo1 is cancelled while vo2 is pending
        $this->assertSame('processing', $order->fresh()->status);

        // Progress VendorOrder 2: pending -> confirmed -> packed -> shipped -> delivered
        $statusService->transition($vo2->id, OrderStatus::CONFIRMED, $vendor2->owner_user_id);
        $this->assertSame('processing', $order->fresh()->status);

        $statusService->transition($vo2->id, OrderStatus::PACKED, $vendor2->owner_user_id);
        $statusService->transition($vo2->id, OrderStatus::SHIPPED, $vendor2->owner_user_id, null, null, 'GHN-12345');

        // Shipped commits batch2: on_hand = 20, reserved = 0
        $this->assertSame(20, $batch2->fresh()->qty_on_hand);
        $this->assertSame(0, $batch2->fresh()->qty_reserved);

        // Calling commit a second time is idempotent
        $inventoryService->commitForVendorOrder($vo2->id, $vendor2->owner_user_id);
        $this->assertSame(20, $batch2->fresh()->qty_on_hand);

        Carbon::setTestNow(Carbon::parse('2026-11-07 10:00:00'));
        $statusService->transition($vo2->id, OrderStatus::DELIVERED, $vendor2->owner_user_id);

        // After 6 days -> not yet auto-completed
        Carbon::setTestNow(Carbon::parse('2026-11-13 09:00:00'));
        $this->artisan('orders:complete')->assertExitCode(0);
        $this->assertSame('delivered', $vo2->fresh()->status);

        // After 7 days -> auto-completed and parent order becomes 'completed'
        Carbon::setTestNow(Carbon::parse('2026-11-14 10:01:00'));
        $this->artisan('orders:complete')->assertExitCode(0);
        $this->assertSame('completed', $vo2->fresh()->status);
        $this->assertSame('completed', $order->fresh()->status);

        Carbon::setTestNow();
    }
}
