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
use App\Services\Inventory\InventoryException;
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
        foreach ([[$sku1, 10000], [$sku2, 40000], [$sku3, 15000]] as [$sku, $commission]) {
            $this->assertDatabaseHas('vendor_orders', [
                'order_id' => $order->id,
                'vendor_id' => $sku->product->vendor_id,
                'commission_amount' => $commission,
            ]);
            $this->assertDatabaseHas('order_items', [
                'sku_id' => $sku->id,
                'commission_amount' => $commission,
            ]);
        }
        $this->assertDatabaseCount('order_item_allocations', 3);
        $this->assertDatabaseCount('stock_movements', 3);
        $this->assertSame([1, 2, 1], Batch::query()->orderBy('id')->pluck('qty_reserved')->all());

        // Re-submit the same key after checkout has cleared the cart.
        $this->post(route('customer.checkout.store'), [
            'address_id' => $address->id,
            'idempotency_key' => $idempotencyKey,
        ])->assertRedirect(route('customer.orders.show', $order));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('vendor_orders', 3);
        $this->assertDatabaseCount('order_items', 3);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('order_item_allocations', 3);
        $this->assertDatabaseCount('stock_movements', 3);
        $this->assertSame([1, 2, 1], Batch::query()->orderBy('id')->pluck('qty_reserved')->all());
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

        $item = $order->vendorOrders->sole()->items->sole();
        $allocations = $item->allocations->keyBy('batch_id');
        $this->assertDatabaseCount('order_item_allocations', 2);
        $this->assertDatabaseCount('stock_movements', 2);

        foreach ([[$nearBatch, 100], [$farBatch, 50]] as [$batch, $quantity]) {
            $this->assertArrayHasKey($batch->id, $allocations->all());
            $allocation = $allocations->get($batch->id);
            $this->assertSame($quantity, $allocation->qty);
            $this->assertSame('held', $allocation->status);
            $this->assertDatabaseHas('stock_movements', [
                'batch_id' => $batch->id,
                'type' => 'reserve',
                'qty' => -$quantity,
                'ref_type' => 'order_item_allocation',
                'ref_id' => $allocation->id,
            ]);
        }
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

        $checkoutService = app(CheckoutService::class);
        $order = $checkoutService->place($customer1->id, $address1->id, (string) Str::uuid());

        $this->assertSame(1, $batch->fresh()->qty_reserved);
        $this->assertDatabaseCount('orders', 1);

        $secondKey = (string) Str::uuid();
        try {
            $checkoutService->place($customer2->id, $address2->id, $secondKey);
            $this->fail('The second checkout should throw InventoryException.');
        } catch (InventoryException $exception) {
            $this->assertStringContainsString($sku->sku_code, $exception->getMessage());
        }
        $this->assertDatabaseMissing('orders', ['idempotency_key' => $secondKey]);

        // Customer 2 must receive a validation error on 'cart' rather than a 500 error.
        $this->actingAs($customer2)
            ->from(route('customer.checkout.index'))
            ->post(route('customer.checkout.store'), [
                'address_id' => $address2->id,
                'idempotency_key' => $secondKey,
            ])
            ->assertRedirect(route('customer.checkout.index'))
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'customer_id' => $customer1->id]);
        $this->assertDatabaseCount('vendor_orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('order_item_allocations', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('order_status_history', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $customer2->cart()->sole()->id,
            'sku_id' => $sku->id,
            'qty' => 1,
        ]);
        $this->assertSame(1, $batch->fresh()->qty_reserved);
        $this->assertSame(1, $batch->fresh()->qty_on_hand);
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

    public function test_vendor_orders_release_and_commit_once_and_auto_complete_the_parent(): void
    {
        $customer = User::factory()->customer()->create();
        $address = $this->createAddress($customer);
        $cancelSku = $this->createSellableSku(110000, 10);
        $shipSku = $this->createSellableSku(120000, 12);
        $cancelBatch = Batch::query()->where('sku_id', $cancelSku->id)->sole();
        $shipBatch = Batch::query()->where('sku_id', $shipSku->id)->sole();

        $cartService = app(CartService::class);
        $cartService->addItem($customer->id, $cancelSku->id, 4);
        $cartService->addItem($customer->id, $shipSku->id, 5);
        $order = app(CheckoutService::class)->place($customer->id, $address->id, (string) Str::uuid());
        $cancelOrder = $order->vendorOrders()->where('vendor_id', $cancelSku->product->vendor_id)->sole();
        $shipOrder = $order->vendorOrders()->where('vendor_id', $shipSku->product->vendor_id)->sole();
        $cancelOwner = $cancelOrder->vendor->owner;
        $shipOwner = $shipOrder->vendor->owner;
        $orderStatusService = app(OrderStatusService::class);
        $inventoryService = app(InventoryService::class);

        $this->assertSame(4, $cancelBatch->fresh()->qty_reserved);
        $this->assertSame(5, $shipBatch->fresh()->qty_reserved);

        $orderStatusService->transition($cancelOrder, OrderStatus::CANCELLED, $cancelOwner, 'Cửa hàng hủy đơn');
        $this->assertSame('cancelled', $cancelOrder->fresh()->status);
        $this->assertSame('pending', $shipOrder->fresh()->status);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame(0, $cancelBatch->fresh()->qty_reserved);
        $this->assertSame(5, $shipBatch->fresh()->qty_reserved);
        $this->assertDatabaseHas('stock_movements', [
            'batch_id' => $cancelBatch->id,
            'type' => 'release',
            'qty' => 4,
        ]);

        $inventoryService->releaseForVendorOrder($cancelOrder, $cancelOwner->id);
        $this->assertSame(0, $cancelBatch->fresh()->qty_reserved);
        $this->assertSame(10, $cancelBatch->fresh()->qty_on_hand);
        $this->assertSame('released', $cancelBatch->allocations()->sole()->status);
        $this->assertDatabaseCount('stock_movements', 3);

        $orderStatusService->transition($shipOrder, OrderStatus::CONFIRMED, $shipOwner);
        $orderStatusService->transition($shipOrder, OrderStatus::PACKED, $shipOwner);
        $orderStatusService->transition($shipOrder, OrderStatus::SHIPPED, $shipOwner);
        $this->assertSame('shipped', $shipOrder->fresh()->status);
        $this->assertSame(7, $shipBatch->fresh()->qty_on_hand);
        $this->assertSame(0, $shipBatch->fresh()->qty_reserved);
        $this->assertDatabaseHas('stock_movements', [
            'batch_id' => $shipBatch->id,
            'type' => 'commit',
            'qty' => -5,
        ]);

        $inventoryService->commitForVendorOrder($shipOrder, $shipOwner->id);
        $this->assertSame(7, $shipBatch->fresh()->qty_on_hand);
        $this->assertSame(0, $shipBatch->fresh()->qty_reserved);
        $this->assertSame('committed', $shipBatch->allocations()->sole()->status);
        $this->assertDatabaseCount('stock_movements', 4);

        $orderStatusService->transition($shipOrder, OrderStatus::DELIVERED, $shipOwner);
        $this->assertSame('processing', $order->fresh()->status);
        $this->artisan('orders:complete')->assertExitCode(0);
        $this->assertSame('delivered', $shipOrder->fresh()->status);

        $shipOrder->update(['delivered_at' => now()->subDays(8)]);
        $this->artisan('orders:complete')->assertExitCode(0);
        $this->assertSame('completed', $shipOrder->fresh()->status);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('cancelled', $cancelOrder->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 4);
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
