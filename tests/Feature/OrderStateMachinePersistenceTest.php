<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Services\OrderStateMachine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OrderStateMachinePersistenceTest extends TestCase
{
    use RefreshDatabase;

    private OrderStateMachine $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OrderStateMachine();
    }

    private function createVendorOrder(): VendorOrder
    {
        $customer = User::factory()->create();

        $vendor = Vendor::factory()
            ->approved()
            ->create();

        $order = Order::create([
            'order_code' => 'ORD-' . Str::random(12),
            'customer_id' => $customer->id,
            'receiver' => 'Test Customer',
            'phone' => '0900000000',
            'address_text' => 'Test Address',
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

        return VendorOrder::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'vo_code' => 'VO-' . Str::random(12),
            'status' => 'pending',
            'subtotal' => 150000,
            'shipping_fee' => 0,
            'discount' => 0,
            'total' => 150000,
            'commission_amount' => 15000,
        ]);
    }

    public function test_confirm_updates_order_and_history(): void
    {
        $vendorOrder = $this->createVendorOrder();
        $actor = User::factory()->admin()->create();

        $this->service->transition(
            $vendorOrder->id,
            OrderStatus::CONFIRMED,
            $actor->id,
            'Order confirmed'
        );

        $fresh = $vendorOrder->fresh();

        $this->assertSame('confirmed', $fresh->status);
        $this->assertNotNull($fresh->confirmed_at);

        $this->assertDatabaseHas('order_status_history', [
            'vendor_order_id' => $vendorOrder->id,
            'from_status' => 'pending',
            'to_status' => 'confirmed',
            'changed_by' => $actor->id,
            'note' => 'Order confirmed',
        ]);

        $this->assertDatabaseCount('order_status_history', 1);
    }

    public function test_full_order_lifecycle_updates_timestamps(): void
    {
        $vendorOrder = $this->createVendorOrder();

        $transitions = [
            OrderStatus::CONFIRMED,
            OrderStatus::PACKED,
            OrderStatus::SHIPPED,
            OrderStatus::DELIVERED,
            OrderStatus::COMPLETED,
        ];

        foreach ($transitions as $status) {
            $this->service->transition(
                $vendorOrder->id,
                $status
            );
        }

        $fresh = $vendorOrder->fresh();

        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->confirmed_at);
        $this->assertNotNull($fresh->shipped_at);
        $this->assertNotNull($fresh->delivered_at);
        $this->assertNotNull($fresh->completed_at);

        $histories = DB::table('order_status_history')
            ->where('vendor_order_id', $vendorOrder->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(5, $histories);

        $this->assertSame(
            [
                'confirmed',
                'packed',
                'shipped',
                'delivered',
                'completed',
            ],
            $histories->pluck('to_status')->all()
        );

        $this->assertSame(
            [
                'pending',
                'confirmed',
                'packed',
                'shipped',
                'delivered',
            ],
            $histories->pluck('from_status')->all()
        );
    }

    public function test_cancellation_saves_reason_and_history(): void
    {
        $vendorOrder = $this->createVendorOrder();

        $this->service->transition(
            $vendorOrder->id,
            OrderStatus::CANCELLED,
            null,
            'Customer requested cancellation',
            'Customer changed mind'
        );

        $fresh = $vendorOrder->fresh();

        $this->assertSame('cancelled', $fresh->status);

        $this->assertSame(
            'Customer changed mind',
            $fresh->cancel_reason
        );

        $this->assertDatabaseHas('order_status_history', [
            'vendor_order_id' => $vendorOrder->id,
            'from_status' => 'pending',
            'to_status' => 'cancelled',
        ]);
    }

    public function test_invalid_transition_preserves_database(): void
    {
        $vendorOrder = $this->createVendorOrder();

        try {
            $this->service->transition(
                $vendorOrder->id,
                OrderStatus::DELIVERED
            );

            $this->fail('Expected InvalidTransitionException.');
        } catch (InvalidTransitionException $exception) {
            $this->assertStringContainsString(
                'pending',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            'pending',
            $vendorOrder->fresh()->status
        );

        $this->assertDatabaseCount('order_status_history', 0);
    }

    public function test_history_insert_failure_rolls_back_status(): void
    {
        $vendorOrder = $this->createVendorOrder();

        // Invalid user ID violates the changed_by foreign key.
        $invalidUserId = 999999999;

        try {
            $this->service->transition(
                $vendorOrder->id,
                OrderStatus::CONFIRMED,
                $invalidUserId
            );

            $this->fail('Expected database foreign key error.');
        } catch (QueryException $exception) {
            $this->assertNotEmpty(
                $exception->getMessage()
            );
        }

        $fresh = $vendorOrder->fresh();

        $this->assertSame('pending', $fresh->status);
        $this->assertNull($fresh->confirmed_at);

        $this->assertDatabaseCount('order_status_history', 0);
    }
}
