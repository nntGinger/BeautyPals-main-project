<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Settlement;
use App\Models\SettlementLine;
use App\Models\Sku;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettlementAndAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_registration_stores_private_license_and_admin_moderation_records_audit_log(): void
    {
        Storage::fake('local');

        $response = $this->post(route('register.vendor.store'), [
            'name' => 'Lê Minh Khôi',
            'email' => 'newvendor@beautypals.test',
            'phone' => '0988776655',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'store_name' => 'Khôi Derma Store',
            'tax_code' => '0312345999',
            'address' => '12 Nguyễn Thị Minh Khai, Quận 1',
            'license_file' => UploadedFile::fake()->create('giay-phep.pdf', 120, 'application/pdf'),
        ]);

        $response->assertRedirect(route('vendor.pending'));

        $vendor = Vendor::where('store_name', 'Khôi Derma Store')->firstOrFail();
        $this->assertSame('pending', $vendor->status);
        Storage::disk('local')->assertExists($vendor->license_file);

        // Unapproved vendor cannot create products (403)
        $this->get(route('vendor.products.create'))->assertForbidden();

        // Admin moderation: reject without reason is blocked
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->post(route('admin.vendors.reject', $vendor), [
            'reason' => '',
        ])->assertSessionHasErrors('reason');

        $beforeLogsCount = AuditLog::where('entity_type', 'vendor')
            ->where('entity_id', $vendor->id)
            ->where('action', 'vendor.updated')
            ->count();

        // Approve vendor -> generates 1 audit_logs row with old_values & new_values (S1-13)
        $this->post(route('admin.vendors.approve', $vendor))
            ->assertRedirect(route('admin.vendors.show', $vendor));

        $vendor->refresh();
        $this->assertSame('approved', $vendor->status);

        $audit = AuditLog::where('entity_type', 'vendor')
            ->where('entity_id', $vendor->id)
            ->where('action', 'vendor.updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($beforeLogsCount + 1, AuditLog::where('entity_type', 'vendor')->where('entity_id', $vendor->id)->where('action', 'vendor.updated')->count());
        $this->assertSame('pending', $audit->old_values['status'] ?? null);
        $this->assertSame('approved', $audit->new_values['status'] ?? null);
    }

    public function test_settlement_service_matches_hand_calculated_3_orders_is_idempotent_and_enforces_vendor_isolation(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $vendorA = Vendor::factory()->approved()->create(['store_name' => 'Vendor Alpha']);
        $vendorB = Vendor::factory()->approved()->create(['store_name' => 'Vendor Beta']);

        $category = Category::factory()->create(['name' => 'Kem dưỡng', 'slug' => 'kem-duong']);
        $productA = Product::factory()->approved()->create(['vendor_id' => $vendorA->id, 'category_id' => $category->id]);
        $skuA = Sku::factory()->create(['product_id' => $productA->id, 'price' => 100000]);

        $productB = Product::factory()->approved()->create(['vendor_id' => $vendorB->id, 'category_id' => $category->id]);
        $skuB = Sku::factory()->create(['product_id' => $productB->id, 'price' => 200000]);

        $completedAt = Carbon::parse('2026-11-10 14:00:00');

        // Hand-calculated 3 completed VendorOrders:
        // Order 1 (Vendor A): gross = 450,000, commission (10%) = 45,000, net = 405,000
        // Order 2 (Vendor A): gross = 300,000, commission (12%) = 36,000, net = 264,000
        // -> Vendor A Period Total: gross = 750,000, commission = 81,000, net_payout = 669,000
        // Order 3 (Vendor B): gross = 200,000, commission (15%) = 30,000, net_payout = 170,000

        $parentOrder = Order::create([
            'order_code' => 'ORD-SETTLE-001',
            'customer_id' => $customer->id,
            'receiver' => 'Khách Đối Soát',
            'phone' => '0901112222',
            'address_text' => '123 Nguyễn Huệ, Quận 1, TP.HCM',
            'subtotal' => 950000,
            'shipping_total' => 0,
            'discount_total' => 0,
            'grand_total' => 950000,
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'status' => OrderStatus::COMPLETED->value,
            'idempotency_key' => (string) Str::uuid(),
            'placed_at' => $completedAt->copy()->subDays(2),
        ]);

        $vo1 = VendorOrder::create([
            'order_id' => $parentOrder->id,
            'vendor_id' => $vendorA->id,
            'vo_code' => 'VO-SETTLE-A1',
            'status' => OrderStatus::COMPLETED->value,
            'subtotal' => 450000,
            'total' => 450000,
            'commission_amount' => 45000,
            'completed_at' => $completedAt,
        ]);
        OrderItem::create([
            'vendor_order_id' => $vo1->id,
            'sku_id' => $skuA->id,
            'product_name' => 'SP A1',
            'sku_code' => $skuA->sku_code,
            'unit_price' => 450000,
            'qty' => 1,
            'line_total' => 450000,
            'commission_rate' => '10.00',
            'commission_amount' => 45000,
        ]);

        $parentOrder2 = Order::create([
            'order_code' => 'ORD-SETTLE-002',
            'customer_id' => $customer->id,
            'receiver' => 'Khách Đối Soát',
            'phone' => '0901112222',
            'address_text' => '123 Nguyễn Huệ, Quận 1, TP.HCM',
            'subtotal' => 300000,
            'shipping_total' => 0,
            'discount_total' => 0,
            'grand_total' => 300000,
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'status' => OrderStatus::COMPLETED->value,
            'idempotency_key' => (string) Str::uuid(),
            'placed_at' => $completedAt->copy()->subDays(1),
        ]);

        $vo2 = VendorOrder::create([
            'order_id' => $parentOrder2->id,
            'vendor_id' => $vendorA->id,
            'vo_code' => 'VO-SETTLE-A2',
            'status' => OrderStatus::COMPLETED->value,
            'subtotal' => 300000,
            'total' => 300000,
            'commission_amount' => 36000,
            'completed_at' => $completedAt,
        ]);
        OrderItem::create([
            'vendor_order_id' => $vo2->id,
            'sku_id' => $skuA->id,
            'product_name' => 'SP A2',
            'sku_code' => $skuA->sku_code,
            'unit_price' => 150000,
            'qty' => 2,
            'line_total' => 300000,
            'commission_rate' => '12.00',
            'commission_amount' => 36000,
        ]);

        $vo3 = VendorOrder::create([
            'order_id' => $parentOrder->id,
            'vendor_id' => $vendorB->id,
            'vo_code' => 'VO-SETTLE-B1',
            'status' => OrderStatus::COMPLETED->value,
            'subtotal' => 200000,
            'total' => 200000,
            'commission_amount' => 30000,
            'completed_at' => $completedAt,
        ]);
        OrderItem::create([
            'vendor_order_id' => $vo3->id,
            'sku_id' => $skuB->id,
            'product_name' => 'SP B1',
            'sku_code' => $skuB->sku_code,
            'unit_price' => 200000,
            'qty' => 1,
            'line_total' => 200000,
            'commission_rate' => '15.00',
            'commission_amount' => 30000,
        ]);

        $settlementService = app(SettlementService::class);
        $settlements = $settlementService->generate('2026-11-01', '2026-11-15', $admin->id);

        $this->assertCount(2, $settlements);

        $settleA = Settlement::where('vendor_id', $vendorA->id)->firstOrFail();
        $this->assertSame(750000, $settleA->gross_sales);
        $this->assertSame(81000, $settleA->commission_total);
        $this->assertSame(669000, $settleA->net_payout);
        $this->assertSame(2, $settleA->lines()->count());

        $settleB = Settlement::where('vendor_id', $vendorB->id)->firstOrFail();
        $this->assertSame(200000, $settleB->gross_sales);
        $this->assertSame(30000, $settleB->commission_total);
        $this->assertSame(170000, $settleB->net_payout);
        $this->assertSame(1, $settleB->lines()->count());

        // Running generate a second time for the same period does NOT duplicate lines or settlements
        $settlementService->generate('2026-11-01', '2026-11-15', $admin->id);
        $this->assertSame(2, Settlement::count());
        $this->assertSame(3, SettlementLine::count());
        $this->assertSame(669000, $settleA->fresh()->net_payout);

        // Vendor B cannot view Vendor A's settlement (403)
        $this->actingAs($vendorB->owner)
            ->get(route('vendor.settlements.show', $settleA))
            ->assertForbidden();

        // Vendor A can view their own settlement (200)
        $this->actingAs($vendorA->owner)
            ->get(route('vendor.settlements.show', $settleA))
            ->assertOk()
            ->assertSee('669.000');

        // Admin dashboard GMV (950,000) & Platform Revenue (111,000) match direct SQL
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('950.000')
            ->assertSee('111.000');
    }

    public function test_admin_can_lock_and_unlock_user_with_reason_and_audit_log_and_add_commission_rule(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create(['status' => 'active']);
        $category = Category::factory()->create(['name' => 'Son môi', 'slug' => 'son-moi']);

        $this->actingAs($admin);

        // Locking without reason fails validation
        $this->post(route('admin.users.toggle-status', $customer), [
            'reason' => '',
        ])->assertSessionHasErrors('reason');

        // Locking with reason updates status and writes AuditLog
        $this->post(route('admin.users.toggle-status', $customer), [
            'reason' => 'Phát hiện spam đơn hàng ảo',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame('locked', $customer->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'user',
            'entity_id' => $customer->id,
            'action' => 'user.locked',
        ]);

        // Commission rule > 100 rejected
        $this->post(route('admin.commissions.store'), [
            'category_id' => $category->id,
            'rate' => 120,
            'effective_from' => '2026-11-20',
        ])->assertSessionHasErrors('rate');

        // Valid commission rule created and audited
        $this->post(route('admin.commissions.store'), [
            'category_id' => $category->id,
            'rate' => 14.5,
            'effective_from' => '2026-11-20',
        ])->assertRedirect(route('admin.commissions.index'));

        $rule = CommissionRule::where('category_id', $category->id)->firstOrFail();
        $this->assertSame('14.50', (string) $rule->rate);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'commissionrule',
            'entity_id' => $rule->id,
            'action' => 'commissionrule.created',
        ]);
    }
}
