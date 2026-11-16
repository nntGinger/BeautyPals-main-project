<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\Sku;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Services\Inventory\InventoryException;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Isolated integration tests against the REAL BeautyPals migrations.
 * Uses SQLite for deterministic transactional tests; parallel MySQL oversell
 * safety must also be verified on InnoDB (SQLite ignores lockForUpdate()).
 */
class S3InventoryFefoTest extends TestCase
{
    use RefreshDatabase;

    public function test_fefo_allocates_nearest_expiry_first_and_skips_unavailable_batches(): void
    {
        $sku = $this->makeSku();
        $expired = $this->makeBatch($sku, 500, -1);
        $quarantined = $this->makeBatch($sku, 400, 5, 'quarantined');
        $fullReserved = $this->makeBatch($sku, 100, 10, 'active', 100);
        $near = $this->makeBatch($sku, 100, 20);
        $far = $this->makeBatch($sku, 200, 90);
        $item = $this->makeItem($sku, 150);

        $allocations = app(ReservationService::class)->reserve($item, $sku, 150, $item->vendorOrder->order->customer_id);

        $this->assertCount(2, $allocations);
        $this->assertSame([$near->id, $far->id], $allocations->pluck('batch_id')->all());
        $this->assertSame([100, 50], $allocations->pluck('qty')->all());
        $this->assertSame([100, 200], [(int) $near->fresh()->qty_on_hand, (int) $far->fresh()->qty_on_hand]);
        $this->assertSame([100, 50], [(int) $near->fresh()->qty_reserved, (int) $far->fresh()->qty_reserved]);
        $this->assertSame(0, $expired->fresh()->qty_reserved);
        $this->assertSame(0, $quarantined->fresh()->qty_reserved);
        $this->assertSame(100, $fullReserved->fresh()->qty_reserved);
        $this->assertSame(['held', 'held'], $allocations->pluck('status')->all());
        $this->assertEqualsCanonicalizing([-100, -50], StockMovement::query()->pluck('qty')->all());
        $this->assertSame(2, StockMovement::query()->where('type', 'reserve')->count());
    }

    public function test_fefo_uses_batch_id_to_break_ties_in_expiry_date(): void
    {
        $sku = $this->makeSku();
        $first = $this->makeBatch($sku, 2, 30);
        $second = $this->makeBatch($sku, 2, 30);
        $item = $this->makeItem($sku, 3);

        $allocated = app(ReservationService::class)->reserve($item, $sku, 3);

        $this->assertSame([$first->id, $second->id], $allocated->pluck('batch_id')->all());
        $this->assertSame([2, 1], $allocated->pluck('qty')->all());
    }

    public function test_insufficient_inventory_rolls_back_all_reserved_stock_allocations_and_logs(): void
    {
        $sku = $this->makeSku();
        $first = $this->makeBatch($sku, 10, 20);
        $second = $this->makeBatch($sku, 20, 90);
        $item = $this->makeItem($sku, 50);

        try {
            app(ReservationService::class)->reserve($item, $sku, 50);
            $this->fail('Expected InventoryException for 30 units of available stock and 50 requested.');
        } catch (InventoryException $exception) {
            $this->assertStringContainsString('Thiếu 20', $exception->getMessage());
        }

        $this->assertSame(0, $first->fresh()->qty_reserved);
        $this->assertSame(0, $second->fresh()->qty_reserved);
        $this->assertDatabaseCount('order_item_allocations', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_checkout_outer_transaction_rolls_back_order_when_reservation_fails(): void
    {
        $sku = $this->makeSku();
        $batch = $this->makeBatch($sku, 1, 20);
        $beforeOrders = Order::query()->count();

        try {
            DB::transaction(function () use ($sku): void {
                $item = $this->makeItem($sku, 2);
                app(ReservationService::class)->reserve($item, $sku, 2);
            });
            $this->fail('Expected checkout transaction to roll back on insufficient stock.');
        } catch (InventoryException) {
            // Expected: nested reservation exception unwinds the outer checkout.
        }

        $this->assertSame($beforeOrders, Order::query()->count());
        $this->assertDatabaseCount('vendor_orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_item_allocations', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(0, $batch->fresh()->qty_reserved);
    }

    public function test_second_customer_cannot_buy_stock_already_held_by_first_customer(): void
    {
        $sku = $this->makeSku();
        $batch = $this->makeBatch($sku, 1, 20);
        $first = $this->makeItem($sku, 1);
        $second = $this->makeItem($sku, 1);
        $service = app(ReservationService::class);

        $service->reserve($first, $sku, 1);

        $this->expectException(InventoryException::class);
        try {
            $service->reserve($second, $sku, 1);
        } finally {
            $this->assertSame(1, $batch->fresh()->qty_reserved);
            $this->assertSame(1, $batch->fresh()->qty_on_hand);
            $this->assertSame(1, OrderItemAllocation::query()->count());
            $this->assertSame(1, StockMovement::query()->count());
        }
    }

    public function test_repeated_reservation_for_same_order_item_is_blocked(): void
    {
        $sku = $this->makeSku();
        $batch = $this->makeBatch($sku, 4, 20);
        $item = $this->makeItem($sku, 2);
        $service = app(ReservationService::class);
        $service->reserve($item, $sku, 2);

        $this->expectException(InventoryException::class);
        try {
            $service->reserve($item, $sku, 2);
        } finally {
            $this->assertSame(2, $batch->fresh()->qty_reserved);
            $this->assertDatabaseCount('order_item_allocations', 1);
            $this->assertDatabaseCount('stock_movements', 1);
        }
    }

    public function test_reserve_rejects_non_positive_quantity_and_mismatched_sku(): void
    {
        $sku = $this->makeSku();
        $anotherSku = $this->makeSku();
        $item = $this->makeItem($sku, 2);
        $service = app(ReservationService::class);

        foreach ([0, -1] as $qty) {
            try {
                $service->reserve($item, $sku, $qty);
                $this->fail('Expected rejection for non-positive quantity.');
            } catch (InventoryException) {
                // Expected.
            }
        }

        $this->expectException(InventoryException::class);
        try {
            $service->reserve($item, $anotherSku, 2);
        } finally {
            $this->assertDatabaseCount('order_item_allocations', 0);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public function test_commit_on_shipped_decrements_physical_and_reserved_stock_once(): void
    {
        $sku = $this->makeSku();
        $batch = $this->makeBatch($sku, 10, 20);
        $item = $this->makeItem($sku, 3);
        $actor = $item->vendorOrder->order->customer_id;
        app(ReservationService::class)->reserve($item, $sku, 3, $actor);
        $service = app(InventoryService::class);

        $service->commitForVendorOrder($item->vendorOrder, $actor);
        $service->commitForVendorOrder($item->vendorOrder, $actor);
        $service->releaseForVendorOrder($item->vendorOrder, $actor);

        $this->assertSame(7, $batch->fresh()->qty_on_hand);
        $this->assertSame(0, $batch->fresh()->qty_reserved);
        $this->assertSame('committed', $item->allocations()->firstOrFail()->status);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_movements', ['type' => 'reserve', 'qty' => -3, 'created_by' => $actor]);
        $this->assertDatabaseHas('stock_movements', ['type' => 'commit', 'qty' => -3, 'created_by' => $actor]);
    }

    public function test_release_on_cancel_or_reject_restores_available_stock_once(): void
    {
        $sku = $this->makeSku();
        $batch = $this->makeBatch($sku, 10, 20);
        $item = $this->makeItem($sku, 3);
        app(ReservationService::class)->reserve($item, $sku, 3);
        $service = app(InventoryService::class);

        $service->releaseForVendorOrder($item->vendorOrder);
        $service->releaseForVendorOrder($item->vendorOrder);
        $service->commitForVendorOrder($item->vendorOrder);

        $this->assertSame(10, $batch->fresh()->qty_on_hand);
        $this->assertSame(0, $batch->fresh()->qty_reserved);
        $this->assertSame('released', $item->allocations()->firstOrFail()->status);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_movements', ['type' => 'release', 'qty' => 3]);
    }

    public function test_committing_one_vendor_order_does_not_mutate_other_vendor_orders(): void
    {
        $skuA = $this->makeSku();
        $skuB = $this->makeSku();
        $batchA = $this->makeBatch($skuA, 6, 20);
        $batchB = $this->makeBatch($skuB, 7, 20);
        $sharedOrder = $this->makeOrder();
        $itemA = $this->makeItem($skuA, 2, $sharedOrder);
        $itemB = $this->makeItem($skuB, 3, $sharedOrder);
        $reservation = app(ReservationService::class);
        $reservation->reserve($itemA, $skuA, 2);
        $reservation->reserve($itemB, $skuB, 3);

        app(InventoryService::class)->commitForVendorOrder($itemA->vendorOrder);

        $this->assertSame(4, $batchA->fresh()->qty_on_hand);
        $this->assertSame(0, $batchA->fresh()->qty_reserved);
        $this->assertSame(7, $batchB->fresh()->qty_on_hand);
        $this->assertSame(3, $batchB->fresh()->qty_reserved);
        $this->assertSame('held', $itemB->allocations()->firstOrFail()->status);
        $this->assertSame(1, StockMovement::query()->where('type', 'commit')->count());
    }

    public function test_commit_rolls_back_all_batches_if_one_batch_has_corrupted_reservation(): void
    {
        $sku = $this->makeSku();

        $first = $this->makeBatch($sku, 2, 20);
        $second = $this->makeBatch($sku, 2, 90);

        $item = $this->makeItem($sku, 3);

        app(ReservationService::class)->reserve($item, $sku, 3);

        // Giả lập dữ liệu giữ chỗ không nhất quán ở lô thứ hai.
        // Model $second vẫn giữ giá trị cũ (0), trong khi DB đã tăng lên 1.
        // Làm mới Model để Eloquent thực sự ghi thay đổi từ 1 về 0.
        $second->refresh()->update(['qty_reserved' => 0]);

        // Kiểm tra dữ liệu TRƯỚC khi commit.
        $this->assertSame(
            2,
            $first->fresh()->qty_on_hand,
            'Lô thứ nhất sai tồn vật lý trước commit.'
        );

        $this->assertSame(
            2,
            $first->fresh()->qty_reserved,
            'Lô thứ nhất sai tồn giữ chỗ trước commit.'
        );

        $this->assertSame(0, $second->fresh()->qty_reserved);

        $this->assertDatabaseHas('order_item_allocations', [
            'batch_id' => $second->id,
            'qty' => 1,
            'status' => 'held',
        ]);

        // Kiểm tra Service có phát hiện dữ liệu sai hay không.
        try {
            app(InventoryService::class)
                ->commitForVendorOrder($item->vendorOrder);

            $this->fail(
                'Commit không ném InventoryException khi dữ liệu giữ chỗ bị sai.'
            );
        } catch (InventoryException $exception) {
            $this->assertStringContainsString(
                'giữ chỗ',
                $exception->getMessage()
            );
        }

        // Sau lỗi, dữ liệu phải giữ nguyên.
        $this->assertSame(2, $first->fresh()->qty_on_hand);
        $this->assertSame(2, $first->fresh()->qty_reserved);

        $this->assertSame(2, $second->fresh()->qty_on_hand);
        $this->assertSame(0, $second->fresh()->qty_reserved);

        $this->assertSame(
            2,
            OrderItemAllocation::query()
                ->where('status', 'held')
                ->count()
        );

        $this->assertSame(
            0,
            StockMovement::query()
                ->where('type', 'commit')
                ->count()
        );
    }

    private function makeSku(): Sku
    {
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::query()->create([
            'name' => 'Skincare '.Str::random(7),
            'slug' => 'skincare-'.Str::random(14),
        ]);
        $product = Product::query()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Serum '.Str::random(7),
            'slug' => 'serum-'.Str::random(14),
            'status' => 'approved',
        ]);

        return Sku::query()->create([
            'product_id' => $product->id,
            'sku_code' => 'SKU-'.Str::random(16),
            'variant_name' => '100 ml',
            'price' => 150000,
            'is_active' => true,
        ]);
    }

    private function makeBatch(
        Sku $sku,
        int $onHand,
        int $expiresInDays,
        string $status = 'active',
        int $reserved = 0
    ): Batch {
        return Batch::query()->create([
            'sku_id' => $sku->id,
            'batch_no' => 'LOT-'.Str::random(12),
            'mfg_date' => Carbon::today()->subYear()->toDateString(),
            'exp_date' => Carbon::today()->addDays($expiresInDays)->toDateString(),
            'qty_received' => $onHand,
            'qty_on_hand' => $onHand,
            'qty_reserved' => $reserved,
            'status' => $status,
        ]);
    }

    private function makeOrder(): Order
    {
        $customer = User::factory()->create(['role' => 'customer']);

        return Order::query()->create([
            'order_code' => 'ORD-'.Str::random(16),
            'customer_id' => $customer->id,
            'receiver' => 'Test Customer',
            'phone' => '0900000000',
            'address_text' => 'Test Address, HCM',
            'subtotal' => 150000,
            'shipping_total' => 0,
            'discount_total' => 0,
            'grand_total' => 150000,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'idempotency_key' => (string) Str::uuid(),
            'placed_at' => now(),
        ]);
    }

    private function makeItem(Sku $sku, int $quantity, ?Order $order = null): OrderItem
    {
        $vendor = $sku->product->vendor;
        $order ??= $this->makeOrder();
        $vendorOrder = VendorOrder::query()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'vo_code' => 'VO-'.Str::random(16),
            'status' => 'pending',
            'subtotal' => $sku->price * $quantity,
            'shipping_fee' => 0,
            'discount' => 0,
            'total' => $sku->price * $quantity,
            'commission_amount' => 0,
        ]);

        return OrderItem::query()->create([
            'vendor_order_id' => $vendorOrder->id,
            'sku_id' => $sku->id,
            'product_name' => $sku->product->name,
            'sku_code' => $sku->sku_code,
            'unit_price' => $sku->price,
            'qty' => $quantity,
            'line_total' => $sku->price * $quantity,
            'commission_rate' => 0,
            'commission_amount' => 0,
        ]);
    }
}
