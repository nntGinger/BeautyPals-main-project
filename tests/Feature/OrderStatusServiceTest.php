<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\Sku;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Services\Inventory\InventoryService;
use App\Services\OrderStateMachine;
use App\Services\OrderStatusService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class OrderStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $actor;

    private OrderStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->customer()->create();
        $this->actor = User::factory()->admin()->create();
        $this->service = app(OrderStatusService::class);
    }

    #[DataProvider('inventoryTransitions')]
    public function test_transition_calls_the_correct_inventory_operation_once(string $from, string $to, string $method): void
    {
        $vendorOrder = $this->createVendorOrder($from);
        $inventory = Mockery::mock(InventoryService::class);
        $inventory->shouldReceive($method)->once()->withArgs(
            fn (VendorOrder $updated, int $actorId): bool => $updated->is($vendorOrder)
                && $updated->status === $to && $actorId === $this->actor->id,
        );
        $inventory->shouldNotReceive($method === 'commitForVendorOrder' ? 'releaseForVendorOrder' : 'commitForVendorOrder');
        $service = new OrderStatusService(new OrderStateMachine, $inventory);

        $updated = $service->transition($vendorOrder, $to, $this->actor, 'Customer request');

        $this->assertSame($to, $updated->status);
        $this->assertDatabaseHas('order_status_history', [
            'vendor_order_id' => $vendorOrder->id,
            'to_status' => $to,
            'changed_by' => $this->actor->id,
            'note' => 'Customer request',
        ]);
        $this->assertSame($to === 'shipped' ? 'processing' : 'cancelled', $vendorOrder->order->fresh()->status);

        try {
            $service->transition($vendorOrder, $to, $this->actor);
            $this->fail('A duplicate transition must be rejected before touching inventory.');
        } catch (InvalidTransitionException) {
            $this->assertDatabaseCount('order_status_history', 1);
        }
    }

    public static function inventoryTransitions(): array
    {
        return [
            'ship' => ['packed', 'shipped', 'commitForVendorOrder'],
            'cancel' => ['pending', 'cancelled', 'releaseForVendorOrder'],
            'reject' => ['pending', 'rejected', 'releaseForVendorOrder'],
        ];
    }

    public function test_confirmation_does_not_touch_inventory_and_accepts_enum(): void
    {
        $vendorOrder = $this->createVendorOrder();
        $inventory = Mockery::mock(InventoryService::class);
        $inventory->shouldNotReceive('commitForVendorOrder');
        $inventory->shouldNotReceive('releaseForVendorOrder');
        $service = new OrderStatusService(new OrderStateMachine, $inventory);

        $service->transition($vendorOrder, OrderStatus::CONFIRMED, $this->actor);

        $this->assertSame('confirmed', $vendorOrder->fresh()->status);
        $this->assertSame('processing', $vendorOrder->order->fresh()->status);
    }

    public function test_shipping_commits_real_allocations_and_stock_movements(): void
    {
        $vendorOrder = $this->createVendorOrder('packed');
        $allocation = $this->reserveStock($vendorOrder);

        $this->service->transition($vendorOrder, 'shipped', $this->actor);

        $this->assertSame('committed', $allocation->fresh()->status);
        $this->assertSame(8, $allocation->batch->fresh()->qty_on_hand);
        $this->assertSame(0, $allocation->batch->fresh()->qty_reserved);
        $this->assertDatabaseHas('stock_movements', [
            'batch_id' => $allocation->batch_id, 'type' => 'commit',
            'qty' => -2, 'ref_id' => $vendorOrder->id, 'created_by' => $this->actor->id,
        ]);
        $this->assertNotNull($vendorOrder->fresh()->shipped_at);
    }

    public function test_inventory_failure_rolls_back_status_history_and_partially_committed_stock(): void
    {
        $vendorOrder = $this->createVendorOrder('packed');
        $first = $this->reserveStock($vendorOrder);
        $second = $this->reserveStock($vendorOrder, reserved: 0);

        try {
            $this->service->transition($vendorOrder, 'shipped', $this->actor);
            $this->fail('Expected insufficient reserved inventory.');
        } catch (ValidationException) {
            $this->assertSame('packed', $vendorOrder->fresh()->status);
            $this->assertNull($vendorOrder->fresh()->shipped_at);
            $this->assertSame('pending', $vendorOrder->order->fresh()->status);
            $this->assertSame('held', $first->fresh()->status);
            $this->assertSame('held', $second->fresh()->status);
            $this->assertSame(10, $first->batch->fresh()->qty_on_hand);
            $this->assertSame(2, $first->batch->fresh()->qty_reserved);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertDatabaseCount('order_status_history', 0);
        }
    }

    public function test_parent_failure_rolls_back_inventory_and_state_machine_writes(): void
    {
        $vendorOrder = $this->createVendorOrder('packed');
        $allocation = $this->reserveStock($vendorOrder);
        $service = Mockery::mock(OrderStatusService::class, [new OrderStateMachine, new InventoryService])->makePartial();
        $service->shouldReceive('deriveParentStatus')->once()->andThrow(new RuntimeException('Parent update failed'));

        try {
            $service->transition($vendorOrder, 'shipped', $this->actor);
            $this->fail('Expected parent update failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Parent update failed', $exception->getMessage());
            $this->assertSame('packed', $vendorOrder->fresh()->status);
            $this->assertSame('held', $allocation->fresh()->status);
            $this->assertSame(10, $allocation->batch->fresh()->qty_on_hand);
            $this->assertSame(2, $allocation->batch->fresh()->qty_reserved);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertDatabaseCount('order_status_history', 0);
        }
    }

    public function test_customer_cancels_all_pending_children_and_releases_stock(): void
    {
        $first = $this->createVendorOrder();
        $second = $this->createVendorOrder(order: $first->order);
        $allocations = [$this->reserveStock($first), $this->reserveStock($second)];

        $this->service->cancelByCustomer($first->order, $this->customer, 'Changed my mind');

        foreach ([$first, $second] as $vendorOrder) {
            $this->assertSame('cancelled', $vendorOrder->fresh()->status);
            $this->assertSame('Changed my mind', $vendorOrder->fresh()->cancel_reason);
            $this->assertDatabaseHas('order_status_history', [
                'vendor_order_id' => $vendorOrder->id, 'changed_by' => $this->customer->id,
                'note' => 'Changed my mind', 'to_status' => 'cancelled',
            ]);
        }
        foreach ($allocations as $allocation) {
            $this->assertSame('released', $allocation->fresh()->status);
            $this->assertSame(10, $allocation->batch->fresh()->qty_on_hand);
            $this->assertSame(0, $allocation->batch->fresh()->qty_reserved);
        }
        $this->assertSame('cancelled', $first->order->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    #[DataProvider('nonPendingStatuses')]
    public function test_customer_cannot_cancel_if_any_child_is_not_pending(string $status): void
    {
        $first = $this->createVendorOrder();
        $second = $this->createVendorOrder($status, $first->order);
        $allocation = $this->reserveStock($first);
        $staleOrder = $first->order->load('vendorOrders');

        try {
            $this->service->cancelByCustomer($staleOrder, $this->customer, 'Cancel');
            $this->fail('Expected invalid cancellation.');
        } catch (InvalidTransitionException) {
            $this->assertSame('pending', $first->fresh()->status);
            $this->assertSame($status, $second->fresh()->status);
            $this->assertSame('held', $allocation->fresh()->status);
            $this->assertDatabaseCount('order_status_history', 0);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public static function nonPendingStatuses(): array
    {
        return array_map(fn (string $status): array => [$status], [
            'confirmed', 'packed', 'shipped', 'delivered', 'completed', 'cancelled', 'rejected',
        ]);
    }

    public function test_wrong_customer_cannot_cancel(): void
    {
        $vendorOrder = $this->createVendorOrder();

        try {
            $this->service->cancelByCustomer($vendorOrder->order, $this->actor, 'Cancel');
            $this->fail('Expected authorization failure.');
        } catch (AuthorizationException) {
            $this->assertSame('pending', $vendorOrder->fresh()->status);
            $this->assertDatabaseCount('order_status_history', 0);
        }
    }

    public function test_customer_cancellation_uses_current_database_state(): void
    {
        $vendorOrder = $this->createVendorOrder();
        $order = $vendorOrder->order->load('vendorOrders');
        $vendorOrder->update(['status' => 'confirmed']);

        $this->expectException(InvalidTransitionException::class);
        $this->service->cancelByCustomer($order, $this->customer, 'Cancel');
    }

    public function test_failure_on_second_child_rolls_back_entire_customer_cancellation(): void
    {
        $first = $this->createVendorOrder();
        $second = $this->createVendorOrder(order: $first->order);
        $allocation = $this->reserveStock($first);
        $this->reserveStock($second, reserved: 0);

        try {
            $this->service->cancelByCustomer($first->order, $this->customer, 'Cancel');
            $this->fail('Expected second child inventory failure.');
        } catch (ValidationException) {
            $this->assertSame('pending', $first->fresh()->status);
            $this->assertSame('pending', $second->fresh()->status);
            $this->assertSame('pending', $first->order->fresh()->status);
            $this->assertSame('held', $allocation->fresh()->status);
            $this->assertSame(2, $allocation->batch->fresh()->qty_reserved);
            $this->assertDatabaseCount('order_status_history', 0);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    #[DataProvider('parentStatuses')]
    public function test_parent_status_follows_the_four_rules(array $statuses, string $expected): void
    {
        $order = $this->createOrder();
        foreach ($statuses as $status) {
            $this->createVendorOrder($status, $order);
        }

        $this->service->deriveParentStatus($order);

        $this->assertSame($expected, $order->fresh()->status);
    }

    public static function parentStatuses(): array
    {
        return [
            'cancelled and rejected' => [['cancelled', 'rejected'], 'cancelled'],
            'only rejected' => [['rejected'], 'cancelled'],
            'all pending' => [['pending', 'pending'], 'pending'],
            'all completed' => [['completed', 'completed'], 'completed'],
            'completed with inactive siblings' => [['completed', 'cancelled', 'rejected'], 'completed'],
            'pending and cancelled' => [['pending', 'cancelled'], 'processing'],
            'completed and pending' => [['completed', 'pending'], 'processing'],
            'completed and delivered' => [['completed', 'delivered'], 'processing'],
            'all delivered' => [['delivered', 'delivered'], 'processing'],
            'confirmed' => [['confirmed'], 'processing'],
            'packed' => [['packed'], 'processing'],
            'shipped' => [['shipped'], 'processing'],
        ];
    }

    public function test_derivation_ignores_stale_loaded_relations(): void
    {
        $vendorOrder = $this->createVendorOrder();
        $order = $vendorOrder->order->load('vendorOrders');
        $vendorOrder->update(['status' => 'rejected']);

        $this->service->deriveParentStatus($order);

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_complete_command_includes_cutoff_excludes_recent_and_is_idempotent(): void
    {
        $this->travelTo(now()->startOfSecond());
        $old = $this->createVendorOrder('delivered');
        $boundary = $this->createVendorOrder('delivered', $old->order);
        $this->createVendorOrder('cancelled', $old->order);
        $recent = $this->createVendorOrder('delivered');
        $missingDate = $this->createVendorOrder('delivered');
        $shipped = $this->createVendorOrder('shipped');
        $old->update(['delivered_at' => now()->subDays(8)]);
        $boundary->update(['delivered_at' => now()->subDays(7)]);
        $recent->update(['delivered_at' => now()->subDays(7)->addSecond()]);
        $shipped->update(['delivered_at' => now()->subDays(9)]);

        $this->artisan('orders:complete')->expectsOutput('Completed 2 vendor orders.')->assertSuccessful();

        foreach ([$old, $boundary] as $vendorOrder) {
            $this->assertSame('completed', $vendorOrder->fresh()->status);
            $this->assertNotNull($vendorOrder->fresh()->completed_at);
            $this->assertDatabaseHas('order_status_history', [
                'vendor_order_id' => $vendorOrder->id,
                'from_status' => 'delivered', 'to_status' => 'completed', 'changed_by' => null,
            ]);
        }
        $this->assertSame('completed', $old->order->fresh()->status);
        $this->assertSame('delivered', $recent->fresh()->status);
        $this->assertSame('delivered', $missingDate->fresh()->status);
        $this->assertSame('shipped', $shipped->fresh()->status);
        $completedAt = $old->fresh()->completed_at;

        $this->artisan('orders:complete')->expectsOutput('Completed 0 vendor orders.')->assertSuccessful();

        $this->assertTrue($completedAt->eq($old->fresh()->completed_at));
        $this->assertDatabaseCount('order_status_history', 2);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_complete_command_respects_custom_days(): void
    {
        $vendorOrder = $this->createVendorOrder('delivered');
        $vendorOrder->update(['delivered_at' => now()->subDays(3)]);

        $this->artisan('orders:complete', ['--days' => 2])->expectsOutput('Completed 1 vendor orders.')->assertSuccessful();

        $this->assertSame('completed', $vendorOrder->fresh()->status);
    }

    #[DataProvider('invalidDays')]
    public function test_complete_command_rejects_invalid_days(string $days): void
    {
        $this->artisan('orders:complete', ['--days' => $days])
            ->expectsOutput('The --days option must be a non-negative integer.')->assertExitCode(2);
        $this->assertDatabaseCount('order_status_history', 0);
    }

    public static function invalidDays(): array
    {
        return [['-1'], ['abc'], ['1.5']];
    }

    public function test_complete_command_rolls_back_when_parent_update_fails(): void
    {
        $vendorOrder = $this->createVendorOrder('delivered');
        $vendorOrder->update(['delivered_at' => now()->subDays(8)]);
        $service = Mockery::mock(OrderStatusService::class);
        $service->shouldReceive('deriveParentStatus')->once()->andThrow(new RuntimeException('Parent update failed'));
        $this->app->instance(OrderStatusService::class, $service);

        try {
            $this->artisan('orders:complete')->run();
            $this->fail('Expected parent update failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Parent update failed', $exception->getMessage());
            $this->assertSame('delivered', $vendorOrder->fresh()->status);
            $this->assertNull($vendorOrder->fresh()->completed_at);
            $this->assertDatabaseCount('order_status_history', 0);
        }
    }

    public function test_vendor_can_transition_own_order_and_rejection_requires_reason(): void
    {
        $vendorOrder = $this->createVendorOrder();
        $this->actingAs($vendorOrder->vendor->owner);
        $url = route('vendor.orders.transition', $vendorOrder);

        $this->from('/vendor')->post($url, ['status' => 'rejected'])->assertSessionHasErrors('reason');
        $this->assertSame('pending', $vendorOrder->fresh()->status);

        $this->from('/vendor')->post($url, ['status' => 'rejected', 'reason' => 'Unavailable'])
            ->assertRedirect('/vendor')->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('rejected', $vendorOrder->fresh()->status);
        $this->assertDatabaseHas('order_status_history', ['vendor_order_id' => $vendorOrder->id, 'note' => 'Unavailable']);
    }

    public function test_vendor_cannot_transition_another_vendors_order(): void
    {
        $vendorOrder = $this->createVendorOrder();
        $otherVendor = Vendor::factory()->approved()->create();

        $this->actingAs($otherVendor->owner)->post(route('vendor.orders.transition', $vendorOrder), ['status' => 'confirmed'])
            ->assertForbidden();

        $this->assertSame('pending', $vendorOrder->fresh()->status);
        $this->assertDatabaseCount('order_status_history', 0);
    }

    public function test_controller_validates_status_and_handles_invalid_transitions(): void
    {
        $vendorOrder = $this->createVendorOrder();
        $this->actingAs($vendorOrder->vendor->owner);
        $url = route('vendor.orders.transition', $vendorOrder);

        $this->from('/vendor')->post($url, ['status' => 'unknown'])->assertSessionHasErrors('status');
        $this->from('/vendor')->post($url, ['status' => 'shipped'])
            ->assertRedirect('/vendor')->assertSessionHasErrors('status');

        $this->assertSame('pending', $vendorOrder->fresh()->status);
        $this->assertDatabaseCount('order_status_history', 0);
    }

    public function test_controller_returns_friendly_inventory_error_and_rolls_back(): void
    {
        $vendorOrder = $this->createVendorOrder('packed');
        $this->reserveStock($vendorOrder, reserved: 0);

        $this->actingAs($vendorOrder->vendor->owner)->from('/vendor')
            ->post(route('vendor.orders.transition', $vendorOrder), ['status' => 'shipped'])
            ->assertRedirect('/vendor')->assertSessionHasErrors([
                'status' => 'Không thể cập nhật tồn kho. Vui lòng kiểm tra phân bổ lô hàng trước khi thử lại.',
            ]);

        $this->assertSame('packed', $vendorOrder->fresh()->status);
        $this->assertDatabaseCount('order_status_history', 0);
    }

    private function createOrder(): Order
    {
        return Order::create([
            'order_code' => 'ORD-'.Str::random(16),
            'customer_id' => $this->customer->id,
            'receiver' => 'Test Customer', 'phone' => '0900000000', 'address_text' => 'Test Address',
            'subtotal' => 100000, 'grand_total' => 100000,
            'status' => 'pending', 'idempotency_key' => (string) Str::uuid(), 'placed_at' => now(),
        ]);
    }

    private function createVendorOrder(string $status = 'pending', ?Order $order = null): VendorOrder
    {
        $order ??= $this->createOrder();

        return $order->vendorOrders()->create([
            'vendor_id' => Vendor::factory()->approved()->create()->id,
            'vo_code' => 'VO-'.Str::random(16), 'status' => $status, 'subtotal' => 100000, 'total' => 100000,
        ]);
    }

    private function reserveStock(VendorOrder $vendorOrder, int $reserved = 2): OrderItemAllocation
    {
        $category = Category::create(['name' => 'Test', 'slug' => 'cat-'.Str::random(12)]);
        $product = Product::create([
            'vendor_id' => $vendorOrder->vendor_id, 'category_id' => $category->id,
            'name' => 'Test product', 'slug' => 'product-'.Str::random(12), 'status' => 'approved',
        ]);
        $sku = Sku::create([
            'product_id' => $product->id, 'sku_code' => 'SKU-'.Str::random(12),
            'variant_name' => 'Default', 'price' => 50000, 'is_active' => true,
        ]);
        $batch = Batch::create([
            'sku_id' => $sku->id, 'batch_no' => 'LOT-'.Str::random(12),
            'mfg_date' => now()->subMonth(), 'exp_date' => now()->addYear(),
            'qty_received' => 10, 'qty_on_hand' => 10, 'qty_reserved' => $reserved, 'status' => 'active',
        ]);
        $item = $vendorOrder->items()->create([
            'sku_id' => $sku->id, 'product_name' => $product->name, 'sku_code' => $sku->sku_code,
            'unit_price' => 50000, 'qty' => 2, 'line_total' => 100000,
            'commission_rate' => 10, 'commission_amount' => 10000,
        ]);

        return $item->allocations()->create(['batch_id' => $batch->id, 'qty' => 2, 'status' => 'held']);
    }
}
