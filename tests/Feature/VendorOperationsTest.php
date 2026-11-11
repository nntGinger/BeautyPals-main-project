<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Batch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\Sku;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Services\BatchService;
use App\Services\Inventory\InventoryService;
use App\Services\OrderStateMachine;
use Carbon\Carbon;
use Database\Seeders\SprintThreeDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VendorOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendor = Vendor::factory()->approved()->create();
        $sku = Sku::factory()->for(Product::factory()->for($this->vendor))->create();
        $this->batch = Batch::create([
            'sku_id' => $sku->id, 'batch_no' => 'LOT-TEST', 'mfg_date' => today()->subMonth(),
            'exp_date' => today()->addMonth(), 'qty_received' => 20, 'qty_on_hand' => 20,
            'qty_reserved' => 5, 'status' => 'active',
        ]);
        $this->actingAs($this->vendor->owner);
    }

    private function makeOrder(string $status = 'pending', ?Vendor $vendor = null): VendorOrder
    {
        $order = Order::create([
            'order_code' => 'ORD-'.Str::random(12), 'customer_id' => User::factory()->create()->id,
            'receiver' => 'Khách kiểm thử', 'phone' => '0900000000', 'address_text' => 'TP Hồ Chí Minh',
            'subtotal' => 100000, 'grand_total' => 100000, 'status' => 'pending', 'idempotency_key' => (string) Str::uuid(),
        ]);

        return VendorOrder::create([
            'order_id' => $order->id, 'vendor_id' => ($vendor ?? $this->vendor)->id,
            'vo_code' => 'VO-'.Str::random(12), 'status' => $status, 'subtotal' => 100000, 'total' => 100000,
        ]);
    }

    private function allocate(VendorOrder $order): OrderItemAllocation
    {
        $item = OrderItem::create([
            'vendor_order_id' => $order->id, 'sku_id' => $this->batch->sku_id, 'product_name' => 'Sản phẩm kiểm thử',
            'sku_code' => $this->batch->sku->sku_code, 'unit_price' => 20000, 'qty' => 5, 'line_total' => 100000,
            'commission_rate' => 10, 'commission_amount' => 10000,
        ]);

        return $item->allocations()->create(['batch_id' => $this->batch->id, 'qty' => 5, 'status' => 'held']);
    }

    public function test_orders_are_scoped_filtered_and_paginated_with_filter_preserved(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->makeOrder();
        }
        $this->makeOrder('completed');
        $foreign = $this->makeOrder('pending', Vendor::factory()->approved()->create());

        $this->get(route('vendor.orders.index', ['status' => 'pending']))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 16 && $orders->count() === 15 && str_contains($orders->nextPageUrl(), 'status=pending'))
            ->assertDontSee($foreign->vo_code);
        $this->get(route('vendor.orders.index', ['status' => 'pending', 'page' => 2]))
            ->assertOk()->assertViewHas('orders', fn ($orders) => $orders->count() === 1);
        $this->getJson(route('vendor.orders.index', ['status' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_detail_loads_allocations_history_and_only_allowed_buttons(): void
    {
        $order = $this->makeOrder('confirmed');
        $this->allocate($order);
        $order->statusHistories()->create(['from_status' => 'pending', 'to_status' => 'confirmed', 'changed_by' => $this->vendor->owner_user_id, 'note' => 'Đã gọi xác nhận']);
        $this->get(route('vendor.orders.show', $order))->assertOk()->assertSee('LOT-TEST')->assertSee('Đã gọi xác nhận')
            ->assertSee('Đã đóng gói')->assertDontSee('Bàn giao vận chuyển')->assertDontSee('Từ chối đơn')
            ->assertViewHas('vendorOrder', fn ($vo) => $vo->relationLoaded('statusHistory') && $vo->items->first()->allocations->first()->relationLoaded('batch'));
        $this->assertSame([OrderStatus::PACKED], app(OrderStateMachine::class)->allowedTransitionsFor('confirmed'));
    }

    public function test_other_vendor_cannot_view_or_transition_order_or_touch_batch(): void
    {
        $order = $this->makeOrder();
        $other = Vendor::factory()->approved()->create();
        $this->actingAs($other->owner);
        $this->get(route('vendor.orders.show', $order))->assertForbidden();
        $this->post(route('vendor.orders.transition', $order), ['status' => 'confirmed'])->assertForbidden();
        $this->get(route('vendor.inventory.show', $this->batch))->assertForbidden();
        $this->post(route('vendor.inventory.adjust', $this->batch), ['delta' => 5, 'reason' => 'Không được phép'])->assertForbidden();
        $this->get(route('vendor.inventory.index'))->assertOk()->assertDontSee('LOT-TEST');
        $this->assertSame(20, $this->batch->fresh()->qty_on_hand);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_guest_customer_unapproved_and_locked_vendor_are_blocked(): void
    {
        auth()->logout();
        $this->get(route('vendor.orders.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('vendor.orders.index'))->assertForbidden();
        $pending = Vendor::factory()->create();
        $this->actingAs($pending->owner)->get(route('vendor.inventory.index'))->assertRedirect(route('vendor.pending'));
        $this->vendor->owner->update(['status' => 'locked']);
        $this->actingAs($this->vendor->owner->fresh())->get(route('vendor.orders.index'))->assertRedirect(route('login'));
    }

    public function test_transition_validates_reason_and_rejects_invalid_or_stale_moves(): void
    {
        $order = $this->makeOrder();
        $url = route('vendor.orders.transition', $order);
        foreach (['', '   '] as $reason) {
            $this->postJson($url, ['status' => 'rejected', 'reason' => $reason])->assertUnprocessable()->assertJsonValidationErrors('reason');
        }
        $this->postJson($url, ['status' => 'shipped'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson($url, ['status' => 'made-up'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseCount('order_status_history', 0);
        $this->post($url, ['status' => 'confirmed'])->assertRedirect(route('vendor.orders.show', $order));
        $this->postJson($url, ['status' => 'confirmed'])->assertUnprocessable();
        $this->assertDatabaseCount('order_status_history', 1);
        $this->assertSame('processing', $order->order->fresh()->status);
    }

    public function test_rejection_records_actor_reason_releases_stock_and_closes_order(): void
    {
        $order = $this->makeOrder();
        $allocation = $this->allocate($order);
        $this->post(route('vendor.orders.transition', $order), ['status' => 'rejected', 'reason' => 'Hàng lỗi khi kiểm tra'])
            ->assertRedirect(route('vendor.orders.show', $order));
        $this->assertDatabaseHas('order_status_history', ['vendor_order_id' => $order->id, 'to_status' => 'rejected', 'note' => 'Hàng lỗi khi kiểm tra', 'changed_by' => $this->vendor->owner_user_id]);
        $this->assertSame('released', $allocation->fresh()->status);
        $this->assertSame(0, $this->batch->fresh()->qty_reserved);
        $this->assertSame(20, $this->batch->fresh()->qty_on_hand);
        $this->assertSame('cancelled', $order->order->fresh()->status);
        app(InventoryService::class)->releaseForVendorOrder($order);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_full_lifecycle_commits_stock_exactly_once_and_shows_no_final_actions(): void
    {
        $order = $this->makeOrder();
        $allocation = $this->allocate($order);
        foreach (['confirmed', 'packed', 'shipped', 'delivered', 'completed'] as $status) {
            $this->post(route('vendor.orders.transition', $order), ['status' => $status])->assertRedirect(route('vendor.orders.show', $order));
        }
        app(InventoryService::class)->commitForVendorOrder($order);
        $this->assertSame('committed', $allocation->fresh()->status);
        $this->assertSame(15, $this->batch->fresh()->qty_on_hand);
        $this->assertSame(0, $this->batch->fresh()->qty_reserved);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('order_status_history', 5);
        $this->assertSame('completed', $order->order->fresh()->status);
        $this->get(route('vendor.orders.show', $order))->assertOk()->assertSee('Đơn đã kết thúc')->assertDontSee('name="status"', false);
    }

    public function test_inventory_failure_rolls_back_status_timestamp_and_history(): void
    {
        $order = $this->makeOrder('packed');
        $this->allocate($order);
        $this->batch->update(['qty_reserved' => 0]);
        $this->postJson(route('vendor.orders.transition', $order), ['status' => 'shipped'])->assertUnprocessable();
        $this->assertSame('packed', $order->fresh()->status);
        $this->assertNull($order->fresh()->shipped_at);
        $this->assertDatabaseCount('order_status_history', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_adjustments_preserve_reserved_stock_and_audit_signed_delta_reason_actor(): void
    {
        $url = route('vendor.inventory.adjust', $this->batch);
        $this->post($url, ['delta' => 3, 'reason' => 'Kiểm kê thừa 3'])->assertRedirect(route('vendor.inventory.show', $this->batch));
        $this->post($url, ['delta' => -18, 'reason' => 'Kiểm kê thiếu 18'])->assertRedirect();
        $this->assertSame(5, $this->batch->fresh()->qty_on_hand);
        $this->assertSame(5, $this->batch->fresh()->qty_reserved);
        $this->assertSame(20, $this->batch->fresh()->qty_received);
        $this->assertDatabaseHas('stock_movements', ['batch_id' => $this->batch->id, 'type' => 'adjust', 'qty' => -18, 'reason' => 'Kiểm kê thiếu 18', 'created_by' => $this->vendor->owner_user_id]);
        $this->get(route('vendor.inventory.show', $this->batch))->assertOk()->assertSee('Kiểm kê thiếu 18')->assertSee('-18');
        $this->postJson($url, ['delta' => -1, 'reason' => 'Vượt giữ chỗ'])->assertUnprocessable()->assertJsonValidationErrors('delta');
        $this->assertSame(5, $this->batch->fresh()->qty_on_hand);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_adjust_rejects_empty_reason_zero_fraction_overflow_and_negative_stock(): void
    {
        $url = route('vendor.inventory.adjust', $this->batch);
        foreach ([['delta' => 1], ['delta' => 1, 'reason' => '  '], ['delta' => 0, 'reason' => 'Zero'], ['delta' => 1.5, 'reason' => 'Fraction'], ['delta' => 2147483648, 'reason' => 'Overflow'], ['delta' => -21, 'reason' => 'Negative']] as $payload) {
            $this->postJson($url, $payload)->assertUnprocessable();
        }
        $this->assertSame(20, $this->batch->fresh()->qty_on_hand);
        $this->assertDatabaseCount('stock_movements', 0);

        try {
            app(BatchService::class)->adjust($this->batch, 1, '   ', $this->vendor->owner_user_id);
            $this->fail('Service must require a reason independently of HTTP validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }
    }

    public function test_audit_insert_failure_rolls_back_batch_adjustment(): void
    {
        try {
            app(BatchService::class)->adjust($this->batch, 3, 'Invalid actor', 999999999);
            $this->fail('Expected foreign key violation.');
        } catch (QueryException) {
            $this->assertSame(20, $this->batch->fresh()->qty_on_hand);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public function test_demo_seeder_is_repeatable_without_resetting_work(): void
    {
        $this->seed(SprintThreeDemoSeeder::class);
        $batch = Batch::where('batch_no', 'S3-LOT-NEAR')->firstOrFail();
        $order = VendorOrder::where('vo_code', 'S3-VO-001')->firstOrFail();
        $order->update(['status' => 'confirmed']);
        app(BatchService::class)->adjust($batch, 3, 'Kiểm kê demo', $this->vendor->owner_user_id);
        $count = StockMovement::count();

        $this->seed(SprintThreeDemoSeeder::class);
        $this->assertSame(20, VendorOrder::where('vo_code', 'like', 'S3-VO-%')->count());
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(101, $batch->fresh()->qty_on_hand);
        $this->assertSame(36, $batch->fresh()->qty_reserved);
        $this->assertSame($count, StockMovement::count());
    }

    public function test_expire_command_includes_today_skips_inactive_future_and_is_idempotent(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 18:00:00', 'UTC'));
        // Business date is already Nov 13 in Vietnam, including before server midnight.
        $fixtures = [['2026-11-12', 'active'], ['2026-11-13', 'active'], ['2026-11-14', 'active'], ['2026-11-12', 'blocked'], ['2026-11-12', 'expired']];
        foreach ($fixtures as $i => [$date, $status]) {
            $this->batch->replicate()->fill(['batch_no' => 'EXP-'.$i, 'exp_date' => $date, 'status' => $status])->save();
        }
        $this->artisan('batches:expire')->expectsOutput('Đã đánh dấu 2 lô hết hạn.')->assertSuccessful();
        $this->assertDatabaseHas('batches', ['batch_no' => 'EXP-1', 'status' => 'expired', 'qty_on_hand' => 20, 'qty_reserved' => 5]);
        $this->assertDatabaseHas('batches', ['batch_no' => 'EXP-2', 'status' => 'active']);
        $this->assertDatabaseHas('batches', ['batch_no' => 'EXP-3', 'status' => 'blocked']);
        $this->artisan('batches:expire')->expectsOutput('Đã đánh dấu 0 lô hết hạn.')->assertSuccessful();
        $event = collect(Schedule::events())->first(fn ($event) => str_contains($event->command ?? '', 'batches:expire'));
        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertSame('Asia/Ho_Chi_Minh', $event->timezone);
    }
}
