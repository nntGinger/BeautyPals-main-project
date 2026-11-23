<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Settlement;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Services\SettlementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_totals_only_completed_orders_in_the_period_and_is_idempotent(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $first = $this->createVendorOrder($vendor, 450000, 45000, '2026-05-01 00:00:00');
        $second = $this->createVendorOrder($vendor, 200000, 30000, '2026-05-15 12:00:00');
        $third = $this->createVendorOrder($vendor, 350000, 35000, '2026-05-31 23:59:59');
        $delivered = $this->createVendorOrder($vendor, 75000, 7500, '2026-05-20 12:00:00', OrderStatus::DELIVERED);
        $before = $this->createVendorOrder($vendor, 80000, 8000, '2026-04-30 23:59:59');
        $after = $this->createVendorOrder($vendor, 90000, 9000, '2026-06-01 00:00:00');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $service = app(SettlementService::class);

        $start = Carbon::parse('2026-05-01 12:00:00');
        $end = Carbon::parse('2026-05-31 12:00:00');
        $result = $service->generate($start, $end);

        $this->assertCount(1, $result);
        $settlement = $result->sole()->fresh();
        $this->assertSame('pending', $settlement->status);
        $this->assertSame('2026-05-01', $settlement->period_start->toDateString());
        $this->assertSame('2026-05-31', $settlement->period_end->toDateString());
        $this->assertSame('12:00:00', $start->toTimeString());
        $this->assertSame('12:00:00', $end->toTimeString());
        $this->assertSame(1000000, $settlement->gross_sales);
        $this->assertSame(110000, $settlement->commission_total);
        $this->assertSame(890000, $settlement->net_payable);
        $this->assertSame(890000, $settlement->net_payout);
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id, $third->id],
            $settlement->lines()->pluck('vendor_order_id')->all(),
        );
        foreach ([$delivered, $before, $after] as $excluded) {
            $this->assertDatabaseMissing('settlement_lines', ['vendor_order_id' => $excluded->id]);
        }
        $this->assertTrue($service->generate('2026-05-01', '2026-05-31')->isEmpty());
        $this->assertTrue($service->generate('2026-05-10', '2026-05-20')->isEmpty());
        $this->assertDatabaseCount('settlements', 1);
        $this->assertDatabaseCount('settlement_lines', 3);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'settlement.generate',
            'entity_type' => 'settlement',
            'entity_id' => $settlement->id,
        ]);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_generate_with_a_vendor_id_excludes_other_vendors(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $other = Vendor::factory()->approved()->create();
        $order = $this->createVendorOrder($vendor, 100000, 10000, '2026-05-15');
        $otherOrder = $this->createVendorOrder($other, 200000, 20000, '2026-05-15');

        $settlement = app(SettlementService::class)->generate('2026-05-01', '2026-05-31', $vendor->id)->sole();

        $this->assertSame($vendor->id, $settlement->vendor_id);
        $this->assertSame($order->id, $settlement->lines()->sole()->vendor_order_id);
        $this->assertDatabaseMissing('settlement_lines', ['vendor_order_id' => $otherOrder->id]);
        $this->assertDatabaseCount('settlements', 1);
    }

    public function test_generate_updates_pending_totals_and_skips_paid_settlements(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $this->createVendorOrder($vendor, 100000, 150000, '2026-05-01');
        $service = app(SettlementService::class);
        $settlement = $service->generate('2026-05-01', '2026-05-31')->sole();
        $this->assertSame(0, $settlement->net_payable);

        $this->createVendorOrder($vendor, 200000, 20000, '2026-05-15');
        $updated = $service->generate('2026-05-01', '2026-05-31')->sole();

        $this->assertTrue($settlement->is($updated));
        $this->assertSame(300000, $updated->gross_sales);
        $this->assertSame(170000, $updated->commission_total);
        $this->assertSame(130000, $updated->net_payable);
        $paid = $service->markPaid($updated);
        $lateOrder = $this->createVendorOrder($vendor, 300000, 30000, '2026-05-31');

        $this->assertTrue($service->generate('2026-05-01', '2026-05-31')->isEmpty());
        $this->assertSame($paid->getAttributes(), $updated->fresh()->getAttributes());
        $this->assertDatabaseCount('settlements', 1);
        $this->assertDatabaseCount('settlement_lines', 2);
        $this->assertDatabaseMissing('settlement_lines', ['vendor_order_id' => $lateOrder->id]);
    }

    public function test_mark_paid_preserves_the_original_payment_timestamp(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $settlement = Settlement::create([
            'vendor_id' => $vendor->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'pending',
        ]);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $service = app(SettlementService::class);

        $this->travelTo(Carbon::parse('2026-06-01 09:00:00'));
        $paid = $service->markPaid($settlement);

        $this->assertSame('paid', $paid->status);
        $this->assertSame('2026-06-01 09:00:00', $paid->paid_at->toDateTimeString());

        $this->travel(1)->day();
        $paidAgain = $service->markPaid($settlement);

        $this->assertTrue($paid->paid_at->equalTo($paidAgain->paid_at));
        $this->assertTrue($paid->paid_at->equalTo($settlement->fresh()->paid_at));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'settlement.mark_paid',
            'entity_type' => 'settlement',
            'entity_id' => $settlement->id,
        ]);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_policy_limits_viewing_to_admin_and_the_approved_owner(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $other = Vendor::factory()->approved()->create();
        $unapproved = Vendor::factory()->pending()->create();
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $settlement = new Settlement(['vendor_id' => $vendor->id]);
        $otherSettlement = new Settlement(['vendor_id' => $other->id]);

        $adminGate = Gate::forUser($admin);
        $this->assertTrue($adminGate->allows('viewAny', Settlement::class));
        $this->assertTrue($adminGate->allows('view', $settlement));
        $this->assertTrue($adminGate->allows('view', $otherSettlement));
        $this->assertTrue($adminGate->allows('generate', Settlement::class));
        $this->assertTrue($adminGate->allows('markPaid', $settlement));

        $ownerGate = Gate::forUser($vendor->owner);
        $this->assertTrue($ownerGate->allows('viewAny', Settlement::class));
        $this->assertTrue($ownerGate->allows('view', $settlement));
        $this->assertTrue(Gate::forUser($other->owner)->denies('view', $settlement));

        foreach ([$vendor->owner, $other->owner, $unapproved->owner, $customer] as $user) {
            $this->assertTrue(Gate::forUser($user)->denies('generate', Settlement::class));
            $this->assertTrue(Gate::forUser($user)->denies('markPaid', $settlement));
        }
        $this->assertTrue(Gate::forUser($unapproved->owner)->denies('viewAny', Settlement::class));
        $this->assertTrue(Gate::forUser($unapproved->owner)->denies('view', new Settlement(['vendor_id' => $unapproved->id])));
        $this->assertTrue(Gate::forUser($customer)->denies('viewAny', Settlement::class));
        $this->assertTrue(Gate::forUser($customer)->denies('view', $settlement));
    }

    private function createVendorOrder(
        Vendor $vendor,
        int $subtotal,
        int $commission,
        string $completedAt,
        OrderStatus $status = OrderStatus::COMPLETED,
    ): VendorOrder {
        $customer = User::factory()->customer()->create();
        $order = Order::create([
            'order_code' => 'ORD-'.Str::random(12),
            'customer_id' => $customer->id,
            'receiver' => 'Test Customer',
            'phone' => '0900000000',
            'address_text' => 'Test Address',
            'subtotal' => $subtotal,
            'grand_total' => $subtotal,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => $status->value,
            'idempotency_key' => (string) Str::uuid(),
            'placed_at' => $completedAt,
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
}
