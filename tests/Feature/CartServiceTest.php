<?php

namespace Tests\Feature;

use App\Exceptions\CartException;
use App\Models\Batch;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sku;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartService $service;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CartService();

        $this->customer = User::factory()
            ->customer()
            ->create();
    }

    /**
     * Create valid product, SKU and inventory data.
     */
    private function createSku(
        ?Vendor $vendor = null,
        int $price = 150000,
        string $productStatus = 'approved',
        bool $active = true,
        int $stock = 10,
        int $reserved = 0,
        string $batchStatus = 'active',
        bool $expired = false
    ): Sku {
        $vendor ??= Vendor::factory()
            ->approved()
            ->create();

        $category = Category::create([
            'name' => 'Test category',
            'slug' => 'category-' . Str::lower(Str::random(12)),
        ]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Test product',
            'slug' => 'product-' . Str::lower(Str::random(12)),
            'status' => $productStatus,
        ]);

        $sku = Sku::create([
            'product_id' => $product->id,
            'sku_code' => 'SKU-' . Str::upper(Str::random(12)),
            'variant_name' => 'Default',
            'price' => $price,
            'is_active' => $active,
        ]);

        Batch::create([
            'sku_id' => $sku->id,
            'batch_no' => 'LOT-' . Str::upper(Str::random(12)),
            'mfg_date' => now()->subYears(2)->toDateString(),
            'exp_date' => (
                $expired ? now()->subDay() : now()->addYear()
            )->toDateString(),
            'qty_received' => $stock,
            'qty_on_hand' => $stock,
            'qty_reserved' => $reserved,
            'status' => $batchStatus,
        ]);

        return $sku;
    }

    public function test_add_item_creates_cart_and_line(): void
    {
        $sku = $this->createSku();

        $item = $this->service->addItem(
            $this->customer->id,
            $sku->id,
            2
        );

        $this->assertSame(2, $item->qty);

        $this->assertDatabaseHas('carts', [
            'user_id' => $this->customer->id,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $item->cart_id,
            'sku_id' => $sku->id,
            'qty' => 2,
        ]);
    }

    public function test_duplicate_sku_accumulates_in_one_line(): void
    {
        $sku = $this->createSku();

        $this->service->addItem(
            $this->customer->id,
            $sku->id,
            2
        );

        $this->service->addItem(
            $this->customer->id,
            $sku->id,
            3
        );

        $this->assertDatabaseCount('cart_items', 1);

        $this->assertSame(
            5,
            CartItem::query()->firstOrFail()->qty
        );
    }

    public function test_update_quantity_replaces_instead_of_adding(): void
    {
        $sku = $this->createSku();

        $this->service->addItem(
            $this->customer->id,
            $sku->id,
            2
        );

        $item = $this->service->updateQuantity(
            $this->customer->id,
            $sku->id,
            7
        );

        $this->assertSame(7, $item->qty);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_remove_item_deletes_only_that_line(): void
    {
        $skuA = $this->createSku();
        $skuB = $this->createSku();

        $this->service->addItem(
            $this->customer->id,
            $skuA->id,
            1
        );

        $this->service->addItem(
            $this->customer->id,
            $skuB->id,
            1
        );

        $this->service->removeItem(
            $this->customer->id,
            $skuA->id
        );

        $this->assertDatabaseMissing('cart_items', [
            'sku_id' => $skuA->id,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'sku_id' => $skuB->id,
        ]);
    }

    public function test_unknown_sku_has_clear_error(): void
    {
        $this->expectException(CartException::class);
        $this->expectExceptionMessage('does not exist');

        $this->service->addItem(
            $this->customer->id,
            999999,
            1
        );
    }

    public function test_inactive_sku_is_rejected(): void
    {
        $sku = $this->createSku(active: false);

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('inactive');

        $this->service->addItem(
            $this->customer->id,
            $sku->id,
            1
        );
    }

    public function test_hidden_product_is_rejected(): void
    {
        $sku = $this->createSku(productStatus: 'hidden');

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('hidden');

        $this->service->addItem(
            $this->customer->id,
            $sku->id,
            1
        );
    }

    public function test_unapproved_vendor_is_rejected(): void
    {
        $vendor = Vendor::factory()
            ->pending()
            ->create();

        $sku = $this->createSku(vendor: $vendor);

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('not approved');

        $this->service->addItem(
            $this->customer->id,
            $sku->id,
            1
        );
    }

    public function test_quantity_cannot_exceed_available_stock(): void
    {
        $sku = $this->createSku(stock: 3);

        $this->expectException(CartException::class);

        $this->expectExceptionMessage(
            'Requested: 4, available: 3'
        );

        $this->service->addItem(
            $this->customer->id,
            $sku->id,
            4
        );
    }

    public function test_reserved_stock_is_not_available(): void
    {
        $sku = $this->createSku(
            stock: 10,
            reserved: 8
        );

        $this->expectException(CartException::class);

        $this->expectExceptionMessage(
            'Requested: 3, available: 2'
        );

        $this->service->addItem(
            $this->customer->id,
            $sku->id,
            3
        );
    }

    public function test_expired_batch_is_not_available(): void
    {
        $sku = $this->createSku(expired: true);

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('available: 0');

        $this->service->addItem(
            $this->customer->id,
            $sku->id,
            1
        );
    }

    public function test_quarantined_batch_is_not_available(): void
    {
        $sku = $this->createSku(
            batchStatus: 'quarantined'
        );

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('available: 0');

        $this->service->addItem(
            $this->customer->id,
            $sku->id,
            1
        );
    }

    public function test_stock_is_combined_across_valid_batches(): void
    {
        $sku = $this->createSku(stock: 2);

        Batch::create([
            'sku_id' => $sku->id,
            'batch_no' => 'LOT-SECOND',
            'mfg_date' => now()->subMonth()->toDateString(),
            'exp_date' => now()->addYear()->toDateString(),
            'qty_received' => 4,
            'qty_on_hand' => 4,
            'qty_reserved' => 1,
            'status' => 'active',
        ]);

        // Available: first batch 2 + second batch 3 = 5.
        $item = $this->service->addItem(
            $this->customer->id,
            $sku->id,
            5
        );

        $this->assertSame(5, $item->qty);
    }

    public function test_three_vendors_produce_three_groups_with_correct_totals(): void
    {
        $skuA = $this->createSku(price: 100000);
        $skuB = $this->createSku(price: 200000);
        $skuC = $this->createSku(price: 300000);

        $this->service->addItem(
            $this->customer->id,
            $skuA->id,
            2
        );

        $this->service->addItem(
            $this->customer->id,
            $skuB->id,
            1
        );

        $this->service->addItem(
            $this->customer->id,
            $skuC->id,
            3
        );

        $result = $this->service->getCartGroupedByVendor(
            $this->customer->id
        );

        $this->assertCount(3, $result['vendors']);

        $this->assertSame(
            6,
            $result['total_quantity']
        );

        $this->assertSame(
            1300000,
            $result['grand_total']
        );

        $this->assertEqualsCanonicalizing(
            [200000, 200000, 900000],
            array_column($result['vendors'], 'subtotal')
        );

        $this->assertCount(
            3,
            array_unique(
                array_column($result['vendors'], 'vendor_id')
            )
        );
    }

    public function test_carts_are_isolated_by_user(): void
    {
        $other = User::factory()
            ->customer()
            ->create();

        $sku = $this->createSku();

        $this->service->addItem(
            $this->customer->id,
            $sku->id,
            2
        );

        $this->service->addItem(
            $other->id,
            $sku->id,
            1
        );

        $first = $this->service->getCartGroupedByVendor(
            $this->customer->id
        );

        $second = $this->service->getCartGroupedByVendor(
            $other->id
        );

        $this->assertSame(2, $first['total_quantity']);
        $this->assertSame(1, $second['total_quantity']);

        $this->assertDatabaseCount('carts', 2);
    }

    public function test_invalid_quantity_is_rejected(): void
    {
        $sku = $this->createSku();

        $this->expectException(CartException::class);

        $this->expectExceptionMessage(
            'greater than zero'
        );

        $this->service->addItem(
            $this->customer->id,
            $sku->id,
            0
        );
    }

    public function test_failed_add_rolls_back_cart_creation(): void
    {
        $sku = $this->createSku(stock: 1);

        try {
            $this->service->addItem(
                $this->customer->id,
                $sku->id,
                5
            );

            $this->fail('Expected CartException.');
        } catch (CartException $exception) {
            $this->assertStringContainsString(
                'Insufficient stock',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('carts', 0);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_failed_update_keeps_previous_quantity(): void
    {
        $sku = $this->createSku(stock: 3);

        $this->service->addItem(
            $this->customer->id,
            $sku->id,
            2
        );

        try {
            $this->service->updateQuantity(
                $this->customer->id,
                $sku->id,
                4
            );

            $this->fail('Expected CartException.');
        } catch (CartException $exception) {
            $this->assertStringContainsString(
                'Insufficient stock',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            2,
            CartItem::query()->firstOrFail()->qty
        );
    }

    public function test_missing_cart_returns_empty_groups(): void
    {
        $result = $this->service->getCartGroupedByVendor(
            $this->customer->id
        );

        $this->assertNull($result['cart_id']);
        $this->assertSame([], $result['vendors']);
        $this->assertSame(0, $result['grand_total']);
        $this->assertSame(0, $result['total_quantity']);
    }
}
