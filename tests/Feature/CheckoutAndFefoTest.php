<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
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
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutAndFefoTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Chăm sóc da',
            'slug' => 'cham-soc-da-'.Str::lower(Str::random(6)),
        ]);

        CommissionRule::create([
            'category_id' => null,
            'rate' => '10.00',
            'effective_from' => today()->subDay(),
            'is_active' => true,
        ]);
    }

    public function test_three_vendor_cart_checkout_via_http_creates_cod_order_and_enforces_idempotency(): void
    {
        $customer = User::factory()->customer()->create();
        $address = $this->createAddress($customer);

        $sku1 = $this->createSellableSku(100000, 10);
        $sku2 = $this->createSellableSku(200000, 10);
        $sku3 = $this->createSellableSku(150000, 10);

        $this->actingAs($customer);

        $this->post(route('customer.cart.store'), ['sku_id' => $sku1->id, 'qty' => 1])->assertRedirect(route('customer.cart.index'));
        $this->post(route('customer.cart.store'), ['sku_id' => $sku2->id, 'qty' => 2])->assertRedirect(route('customer.cart.index'));
        $this->post(route('customer.cart.store'), ['sku_id' => $sku3->id, 'qty' => 1])->assertRedirect(route('customer.cart.index'));

        $this->get(route('customer.checkout.index'))
            ->assertOk()
            ->assertSee('Đặt hàng COD');

        $idempotencyKey = (string) Str::uuid();

        $response = $this->post(route('customer.checkout.store'), [
            'address_id' => $address->id,
            'idempotency_key' => $idempotencyKey,
        ]);

        $order = Order::query()->firstOrFail();

        $response->assertRedirect(route('customer.orders.show', $order))
            ->assertSessionHas('status');

        $this->assertSame('cod', $order->payment_method);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('pending', $order->status);
        $this->assertSame(650000, $order->grand_total);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('vendor_orders', 3);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'cod',
            'amount' => 650000,
            'status' => 'pending',
            'txn_ref' => 'COD-'.$order->order_code,
        ]);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('order_status_history', 3);

        // Re-submitting the same idempotency_key after adding something new does not create a second order.
        $this->post(route('customer.checkout.store'), [
            'address_id' => $address->id,
            'idempotency_key' => $idempotencyKey,
        ])->assertRedirect(route('customer.orders.show', $order));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('vendor_orders', 3);
    }

    public function test_fefo_allocates_100_and_50_from_batches_100_and_200_and_skips_expired_via_checkout(): void
    {
        $customer = User::factory()->customer()->create();
        $address = $this->createAddress($customer);

        $sku = $this->createSellableSku(120000, null);

        $expiredBatch = Batch::create([
            'sku_id' => $sku->id,
            'batch_no' => 'LOT-EXP',
            'mfg_date' => today()->subMonths(6),
            'exp_date' => today()->subDay(),
            'qty_received' => 500,
            'qty_on_hand' => 500,
            'qty_reserved' => 0,
            'status' => 'active',
        ]);

        $nearBatch = Batch::create([
            'sku_id' => $sku->id,
            'batch_no' => 'LOT-NEAR',
            'mfg_date' => today()->subMonth(),
            'exp_date' => today()->addDays(20),
            'qty_received' => 100,
            'qty_on_hand' => 100,
            'qty_reserved' => 0,
            'status' => 'active',
        ]);

        $farBatch = Batch::create([
            'sku_id' => $sku->id,
            'batch_no' => 'LOT-FAR',
            'mfg_date' => today()->subMonth(),
            'exp_date' => today()->addDays(90),
            'qty_received' => 200,
            'qty_on_hand' => 200,
            'qty_reserved' => 0,
            'status' => 'active',
        ]);

        app(CartService::class)->addItem($customer->id, $sku->id, 150);

        $order = app(CheckoutService::class)->place($customer->id, $address->id, (string) Str::uuid());

        $this->assertSame(150 * 120000, $order->grand_total);
        $this->assertSame(0, $expiredBatch->fresh()->qty_reserved);
        $this->assertSame(100, $nearBatch->fresh()->qty_reserved);
        $this->assertSame(50, $farBatch->fresh()->qty_reserved);
    }

    public function test_oversell_when_stock_is_one_allows_first_checkout_and_returns_validation_error_for_second(): void
    {
        $customer1 = User::factory()->customer()->create();
        $customer2 = User::factory()->customer()->create();
        $address1 = $this->createAddress($customer1);
        $address2 = $this->createAddress($customer2);

        $sku = $this->createSellableSku(99000, 1);
        $batch = Batch::query()->where('sku_id', $sku->id)->firstOrFail();

        $cartService = app(CartService::class);
        $cartService->addItem($customer1->id, $sku->id, 1);
        $cartService->addItem($customer2->id, $sku->id, 1);

        $this->actingAs($customer1)
            ->post(route('customer.checkout.store'), [
                'address_id' => $address1->id,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect();

        $this->assertSame(1, $batch->fresh()->qty_reserved);
        $this->assertDatabaseCount('orders', 1);

        // Customer 2 must receive a validation error on 'cart' rather than a 500 error.
        $this->actingAs($customer2)
            ->from(route('customer.checkout.index'))
            ->post(route('customer.checkout.store'), [
                'address_id' => $address2->id,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('customer.checkout.index'))
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, $batch->fresh()->qty_reserved);
    }

    public function test_customer_order_ui_cancel_releases_stock_idempotently_and_prevents_cancel_after_confirm(): void
    {
        $customer = User::factory()->customer()->create();
        $address = $this->createAddress($customer);

        $skuCancel = $this->createSellableSku(100000, 10);
        $batchCancel = Batch::query()->where('sku_id', $skuCancel->id)->firstOrFail();

        app(CartService::class)->addItem($customer->id, $skuCancel->id, 4);
        $cancelOrder = app(CheckoutService::class)->place($customer->id, $address->id, (string) Str::uuid());

        $this->actingAs($customer)
            ->get(route('customer.orders.index'))
            ->assertOk()
            ->assertSee($cancelOrder->order_code);

        $this->actingAs($customer)
            ->get(route('customer.orders.show', $cancelOrder))
            ->assertOk()
            ->assertSee('Hủy đơn hàng')
            ->assertSee($cancelOrder->order_code);

        $this->actingAs($customer)
            ->post(route('customer.orders.cancel', $cancelOrder), ['reason' => 'Đặt nhầm sản phẩm'])
            ->assertRedirect(route('customer.orders.show', $cancelOrder))
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $cancelOrder->fresh()->status);
        $this->assertSame(0, $batchCancel->fresh()->qty_reserved);
        $this->assertSame(10, $batchCancel->fresh()->qty_on_hand);

        // Re-running release directly must stay idempotent.
        app(InventoryService::class)->releaseForVendorOrder($cancelOrder->vendorOrders()->firstOrFail(), $customer->id);
        $this->assertSame(0, $batchCancel->fresh()->qty_reserved);

        // Two-vendor order: once VO1 is confirmed, customer cancel must be denied with 403 and VO2 ships cleanly.
        $sku1 = $this->createSellableSku(110000, 10);
        $sku2 = $this->createSellableSku(120000, 10);
        $batch1 = Batch::query()->where('sku_id', $sku1->id)->firstOrFail();
        $batch2 = Batch::query()->where('sku_id', $sku2->id)->firstOrFail();

        app(CartService::class)->addItem($customer->id, $sku1->id, 4);
        app(CartService::class)->addItem($customer->id, $sku2->id, 4);
        $multiOrder = app(CheckoutService::class)->place($customer->id, $address->id, (string) Str::uuid());

        [$vo1, $vo2] = $multiOrder->vendorOrders()->orderBy('id')->get()->all();
        $orderStatusService = app(OrderStatusService::class);
        $orderStatusService->transition($vo1, OrderStatus::CONFIRMED, $vo1->vendor->owner);

        $this->actingAs($customer)
            ->get(route('customer.orders.show', $multiOrder))
            ->assertOk()
            ->assertDontSee('name="reason"', false);

        $this->actingAs($customer)
            ->post(route('customer.orders.cancel', $multiOrder), ['reason' => 'Không được phép'])
            ->assertForbidden();

        // Cancel VO2 via vendor rejection and ship VO1.
        $orderStatusService->transition($vo2, OrderStatus::REJECTED, $vo2->vendor->owner, 'Hết bao bì');
        $orderStatusService->transition($vo1, OrderStatus::PACKED, $vo1->vendor->owner);
        $orderStatusService->transition($vo1, OrderStatus::SHIPPED, $vo1->vendor->owner);

        $this->assertSame('processing', $multiOrder->fresh()->status);
        $this->assertSame(6, $batch1->fresh()->qty_on_hand);
        $this->assertSame(0, $batch1->fresh()->qty_reserved);
        $this->assertSame(10, $batch2->fresh()->qty_on_hand);
        $this->assertSame(0, $batch2->fresh()->qty_reserved);
    }

    public function test_cart_ui_operations_empty_checkout_redirect_and_customer_order_pagination(): void
    {
        $customer = User::factory()->customer()->create();
        $address = $this->createAddress($customer);
        $sku = $this->createSellableSku(100000, 20);

        $this->actingAs($customer)
            ->get(route('customer.checkout.index'))
            ->assertRedirect(route('customer.cart.index'));

        $this->actingAs($customer)
            ->get(route('customer.cart.index'))
            ->assertOk();

        $this->actingAs($customer)
            ->post(route('customer.cart.store'), ['sku_id' => $sku->id, 'qty' => 2])
            ->assertRedirect(route('customer.cart.index'));

        $this->actingAs($customer)
            ->put(route('customer.cart.update', $sku->id), ['qty' => 3])
            ->assertRedirect(route('customer.cart.index'));

        $this->actingAs($customer)
            ->get(route('customer.cart.index'))
            ->assertOk()
            ->assertSee($sku->product->name);

        $this->actingAs($customer)
            ->delete(route('customer.cart.destroy', $sku->id))
            ->assertRedirect(route('customer.cart.index'));

        // Seed 11 orders to exercise pagination using vendor.partials.pagination without Tailwind SVGs.
        $cartService = app(CartService::class);
        $checkoutService = app(CheckoutService::class);

        for ($i = 0; $i < 11; $i++) {
            $cartService->addItem($customer->id, $sku->id, 1);
            $checkoutService->place($customer->id, $address->id, (string) Str::uuid());
        }

        $this->actingAs($customer)
            ->get(route('customer.orders.index'))
            ->assertOk()
            ->assertSee('Trang sau')
            ->assertDontSee('<svg', false);
    }

    private function createAddress(User $customer): Address
    {
        return Address::create([
            'user_id' => $customer->id,
            'receiver' => $customer->name,
            'phone' => '0900000123',
            'line' => '123 Lê Lợi',
            'ward' => 'Phường Bến Thành',
            'district' => 'Quận 1',
            'city' => 'TP. Hồ Chí Minh',
            'is_default' => true,
        ]);
    }

    private function createSellableSku(int $price, ?int $stock): Sku
    {
        $vendor = Vendor::factory()->approved()->create();
        $product = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $this->category->id,
            'name' => 'Sản phẩm '.Str::random(5),
            'slug' => 'san-pham-'.Str::lower(Str::random(10)),
            'status' => 'approved',
        ]);
        $sku = Sku::create([
            'product_id' => $product->id,
            'sku_code' => 'SKU-'.Str::upper(Str::random(8)),
            'variant_name' => '50ml',
            'price' => $price,
            'is_active' => true,
        ]);

        if ($stock !== null) {
            Batch::create([
                'sku_id' => $sku->id,
                'batch_no' => 'LOT-'.Str::upper(Str::random(6)),
                'mfg_date' => today()->subMonth(),
                'exp_date' => today()->addDays(60),
                'qty_received' => $stock,
                'qty_on_hand' => $stock,
                'qty_reserved' => 0,
                'status' => 'active',
            ]);
        }

        return $sku;
    }
}

