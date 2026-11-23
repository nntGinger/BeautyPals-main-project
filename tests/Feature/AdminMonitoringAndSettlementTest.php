<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Settlement;
use App\Models\SettlementLine;
use App\Models\Sku;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminMonitoringAndSettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->customer = User::factory()->customer()->create();
    }

    public function test_admin_dashboard_computes_gmv_platform_revenue_and_order_counts(): void
    {
        $vendor1 = Vendor::factory()->approved()->create(['store_name' => 'Shop Hoa Hồng']);
        $vendor2 = Vendor::factory()->approved()->create(['store_name' => 'Shop Thảo Mộc']);

        $this->createVendorOrder($vendor1, 'completed', 450000, 45000);
        $this->createVendorOrder($vendor2, 'confirmed', 200000, 30000);
        $this->createVendorOrder($vendor1, 'cancelled', 999000, 99900);
        $this->createVendorOrder($vendor2, 'rejected', 500000, 50000);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertViewHas('gmv', 650000)
            ->assertViewHas('platformRevenue', 75000)
            ->assertViewHas('activeVendorOrdersCount', 2)
            ->assertViewHas('orderCount', 4)
            ->assertSee('650.000 ₫')
            ->assertSee('75.000 ₫')
            ->assertSee('Shop Hoa Hồng');
    }

    public function test_admin_can_monitor_and_filter_platform_orders_and_view_detail_while_others_are_blocked(): void
    {
        $vendor1 = Vendor::factory()->approved()->create(['store_name' => 'Vendor Alpha']);
        $vendor2 = Vendor::factory()->approved()->create(['store_name' => 'Vendor Beta']);

        $category = Category::create([
            'name' => 'Serum',
            'slug' => 'serum-'.Str::lower(Str::random(6)),
        ]);
        $product = Product::create([
            'vendor_id' => $vendor1->id,
            'category_id' => $category->id,
            'name' => 'Tinh chất Niacinamide',
            'slug' => 'tinh-chat-'.Str::lower(Str::random(6)),
            'status' => 'approved',
        ]);
        $sku = Sku::create([
            'product_id' => $product->id,
            'sku_code' => 'SKU-MON-01',
            'variant_name' => '30ml',
            'price' => 300000,
            'is_active' => true,
        ]);
        $batch = Batch::create([
            'sku_id' => $sku->id,
            'batch_no' => 'LOT-MON-01',
            'mfg_date' => today()->subMonth(),
            'exp_date' => today()->addMonths(4),
            'qty_received' => 50,
            'qty_on_hand' => 50,
            'qty_reserved' => 1,
            'status' => 'active',
        ]);

        $vo1 = $this->createVendorOrder($vendor1, 'confirmed', 300000, 36000);
        $item = OrderItem::create([
            'vendor_order_id' => $vo1->id,
            'sku_id' => $sku->id,
            'product_name' => $product->name,
            'sku_code' => $sku->sku_code,
            'unit_price' => 300000,
            'qty' => 1,
            'line_total' => 300000,
            'commission_rate' => '12.00',
            'commission_amount' => 36000,
        ]);
        $item->allocations()->create([
            'batch_id' => $batch->id,
            'qty' => 1,
            'status' => 'held',
        ]);
        $vo1->statusHistories()->create([
            'from_status' => 'pending',
            'to_status' => 'confirmed',
            'changed_by' => $this->admin->id,
            'note' => 'Xác nhận kiểm tra toàn sàn',
        ]);

        $vo2 = $this->createVendorOrder($vendor2, 'pending', 150000, 15000);

        $this->actingAs($this->customer)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($vendor1->owner)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($vendor1->owner)->get(route('admin.orders.show', $vo1))->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('admin.orders.index', ['vendor_id' => $vendor1->id, 'status' => 'confirmed']))
            ->assertOk()
            ->assertSee($vo1->vo_code)
            ->assertDontSee($vo2->vo_code);

        $this->actingAs($this->admin)
            ->get(route('admin.orders.show', $vo1))
            ->assertOk()
            ->assertSee($vo1->vo_code)
            ->assertSee('LOT-MON-01')
            ->assertSee('Xác nhận kiểm tra toàn sàn');
    }

    public function test_admin_can_lock_and_unlock_user_with_audit_log_and_cannot_self_lock(): void
    {
        $target = User::factory()->customer()->create([
            'name' => 'Khách Kiểm Tra 100%',
            'email' => 'target_lock@beautypals.test',
            'status' => 'active',
        ]);

        $this->actingAs($this->customer)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($this->customer)
            ->post(route('admin.users.toggle-status', $target), ['reason' => 'Trái phép'])
            ->assertForbidden();

        // Admin cannot self-lock.
        $this->actingAs($this->admin)
            ->post(route('admin.users.toggle-status', $this->admin), ['reason' => 'Tự khóa'])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error');
        $this->assertSame('active', $this->admin->fresh()->status);

        // Missing or blank reason must fail validation.
        $this->actingAs($this->admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.toggle-status', $target), ['reason' => '   '])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors('reason');
        $this->assertSame('active', $target->fresh()->status);

        // Search treats LIKE wildcards % and _ literally.
        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['q' => '100%']))
            ->assertOk()
            ->assertSee('target_lock@beautypals.test')
            ->assertDontSee($this->admin->email);

        // Lock with valid reason creates audit log and blocks active session.
        $this->actingAs($this->admin)
            ->post(route('admin.users.toggle-status', $target), ['reason' => 'Vi phạm spam đơn'])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertSame('locked', $target->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'user.locked',
            'entity_type' => 'user',
            'entity_id' => $target->id,
        ]);

        // Verify locked account is logged out on next request.
        $this->actingAs($target->fresh())
            ->get(route('customer.dashboard'))
            ->assertRedirect('/login');

        // Unlock restores active status and records user.unlocked audit log.
        $this->actingAs($this->admin)
            ->post(route('admin.users.toggle-status', $target), ['reason' => 'Đã xác minh lại'])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertSame('active', $target->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'user.unlocked',
            'entity_type' => 'user',
            'entity_id' => $target->id,
        ]);
    }

    public function test_admin_and_vendor_settlement_views_enforce_policy_and_handle_missing_service_safely(): void
    {
        $vendorA = Vendor::factory()->approved()->create(['store_name' => 'Shop A']);
        $vendorB = Vendor::factory()->approved()->create(['store_name' => 'Shop B']);
        $voA = $this->createVendorOrder($vendorA, 'completed', 450000, 45000);

        $settlementA = Settlement::create([
            'vendor_id' => $vendorA->id,
            'period_start' => '2026-11-01',
            'period_end' => '2026-11-30',
            'gross_sales' => 450000,
            'commission_total' => 45000,
            'refund_total' => 0,
            'adjustment' => 0,
            'net_payout' => 405000,
            'status' => 'finalized',
            'finalized_by' => $this->admin->id,
        ]);

        SettlementLine::create([
            'settlement_id' => $settlementA->id,
            'vendor_order_id' => $voA->id,
            'gross' => 450000,
            'commission' => 45000,
            'refund' => 0,
            'net' => 405000,
        ]);

        // Vendor A sees own settlement.
        $this->actingAs($vendorA->owner)
            ->get(route('vendor.settlements.index'))
            ->assertOk()
            ->assertSee('Shop A', false)
            ->assertSee('405.000 ₫');

        $this->actingAs($vendorA->owner)
            ->get(route('vendor.settlements.show', $settlementA))
            ->assertOk()
            ->assertSee($voA->vo_code);

        // Vendor B and Customer cannot view Vendor A settlement (403).
        $this->actingAs($vendorB->owner)
            ->get(route('vendor.settlements.show', $settlementA))
            ->assertForbidden();

        $this->actingAs($this->customer)
            ->get(route('vendor.settlements.index'))
            ->assertForbidden();

        $this->actingAs($vendorA->owner)
            ->get(route('admin.settlements.index'))
            ->assertForbidden();

        // Admin views settlements list & detail.
        $this->actingAs($this->admin)
            ->get(route('admin.settlements.index'))
            ->assertOk()
            ->assertSee('Shop A')
            ->assertSee('405.000 ₫');

        $this->actingAs($this->admin)
            ->get(route('admin.settlements.show', $settlementA))
            ->assertOk()
            ->assertSee($voA->vo_code);

        // Before S4-08 SettlementService is merged, generate & markPaid fail gracefully without 500 error.
        $this->actingAs($this->admin)
            ->post(route('admin.settlements.generate'), [
                'period_start' => '2026-11-01',
                'period_end' => '2026-11-30',
            ])
            ->assertRedirect(route('admin.settlements.index'))
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->post(route('admin.settlements.pay', $settlementA))
            ->assertRedirect(route('admin.settlements.show', $settlementA))
            ->assertSessionHas('error');

        // Validation blocks invalid period ranges.
        $this->actingAs($this->admin)
            ->from(route('admin.settlements.index'))
            ->post(route('admin.settlements.generate'), [
                'period_start' => '2026-11-30',
                'period_end' => '2026-11-01',
            ])
            ->assertRedirect(route('admin.settlements.index'))
            ->assertSessionHasErrors('period_end');

        // When SettlementService (S4-08) is bound, generate and markPaid delegate cleanly.
        app()->instance('App\\Services\\SettlementService', new class($settlementA)
        {
            public function __construct(private readonly Settlement $settlement) {}

            public function generate(string $periodStart, string $periodEnd, ?int $vendorId = null): Collection
            {
                return collect([$this->settlement]);
            }

            public function markPaid(Settlement $settlement): Settlement
            {
                $settlement->update(['status' => 'paid', 'paid_at' => now()]);

                return $settlement;
            }
        });

        $this->actingAs($this->admin)
            ->post(route('admin.settlements.generate'), [
                'period_start' => '2026-11-01',
                'period_end' => '2026-11-30',
            ])
            ->assertRedirect(route('admin.settlements.index'))
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post(route('admin.settlements.pay', $settlementA))
            ->assertRedirect(route('admin.settlements.show', $settlementA))
            ->assertSessionHas('success');

        $this->assertSame('paid', $settlementA->fresh()->status);
    }

    public function test_admin_audit_logs_filters_by_actor_action_type_date_and_blocks_non_admin(): void
    {
        $actor1 = User::factory()->admin()->create(['name' => 'Admin A']);
        $actor2 = User::factory()->vendor()->create(['name' => 'Vendor Actor']);

        AuditLog::create([
            'actor_id' => $actor1->id,
            'action' => 'user.locked',
            'entity_type' => 'user',
            'entity_id' => 99,
            'old_values' => ['status' => 'active'],
            'new_values' => ['status' => 'locked', 'reason' => '<script>alert(1)</script>'],
            'ip' => '127.0.0.1',
        ]);

        AuditLog::create([
            'actor_id' => $actor2->id,
            'action' => 'payment_initiated',
            'entity_type' => 'payment',
            'entity_id' => 55,
            'old_values' => null,
            'new_values' => ['amount' => 450000],
            'ip' => '127.0.0.2',
        ]);

        $this->actingAs($this->customer)->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->actingAs($actor2)->get(route('admin.audit-logs.index'))->assertForbidden();

        $response = $this->actingAs($this->admin)->get(route('admin.audit-logs.index', [
            'actor_user_id' => $actor1->id,
            'action' => 'user.locked',
            'auditable_type' => 'user',
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
        ]));

        $response->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1 && $logs->first()->action === 'user.locked')
            ->assertSee('user #99')
            ->assertDontSee('payment #55')
            ->assertDontSee('450000')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<svg', false);
    }

    private function createVendorOrder(
        Vendor $vendor,
        string $status,
        int $subtotal,
        int $commissionAmount,
    ): VendorOrder {
        $order = Order::create([
            'order_code' => 'ORD-'.Str::upper(Str::random(10)),
            'customer_id' => $this->customer->id,
            'receiver' => $this->customer->name,
            'phone' => '0900000999',
            'address_text' => '123 Nguyễn Huệ, TP.HCM',
            'subtotal' => $subtotal,
            'shipping_total' => 0,
            'discount_total' => 0,
            'grand_total' => $subtotal,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => $status === 'completed' ? 'completed' : ($status === 'cancelled' || $status === 'rejected' ? 'cancelled' : 'processing'),
            'idempotency_key' => (string) Str::uuid(),
            'placed_at' => now(),
        ]);

        return VendorOrder::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'vo_code' => 'VO-'.Str::upper(Str::random(10)),
            'status' => $status,
            'subtotal' => $subtotal,
            'shipping_fee' => 0,
            'discount' => 0,
            'total' => $subtotal,
            'commission_amount' => $commissionAmount,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);
    }
}
