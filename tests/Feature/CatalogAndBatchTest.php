<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sku;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Services\Catalog\ProductSearchService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ReservationService;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogAndBatchTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Product $product;

    private Sku $sku;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-23 18:00:00', 'UTC'));
        $this->vendor = Vendor::factory()->approved()->create();
        $this->product = Product::factory()->for($this->vendor)->create();
        $this->sku = Sku::factory()->for($this->product)->create(['price' => 100000]);
        $this->actingAs($this->vendor->owner);
    }

    private function payload(): array
    {
        return [
            'name' => 'Serum kiểm thử', 'category_id' => $this->product->category_id,
            'skus' => [['id' => $this->sku->id, 'sku_code' => $this->sku->sku_code, 'variant_name' => '30ml', 'price' => 120000, 'is_active' => 1]],
            'ingredients' => [],
        ];
    }

    private function batch(): Batch
    {
        return $this->sku->batches()->create([
            'batch_no' => 'LOT-S5', 'mfg_date' => '2026-01-01', 'exp_date' => '2026-12-31',
            'qty_received' => 10, 'qty_on_hand' => 10, 'qty_reserved' => 0, 'status' => 'active',
        ]);
    }

    public function test_catalog_listing_has_bounded_queries_and_separate_deployment_schema_check(): void
    {
        $this->product->update(['status' => 'approved']);
        $this->batch();
        $this->artisan('catalog:check-schema')->expectsOutput('Catalog schema is valid.')->assertSuccessful();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $result = app(ProductSearchService::class)->search(['in_stock' => true]);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
        $this->assertSame(1, $result->total());
        $this->assertCount(2, $queries); // count + page, no per-product or schema queries.
    }

    public function test_create_allowlists_owner_status_and_normalizes_sku(): void
    {
        $payload = $this->payload();
        unset($payload['skus'][0]['id']);
        $payload['skus'][0]['sku_code'] = ' s5-new-sku ';
        $payload += ['vendor_id' => Vendor::factory()->approved()->create()->id, 'status' => 'approved', 'moderated_by' => $this->vendor->owner_user_id];
        $this->post(route('vendor.products.store'), $payload)->assertRedirect();
        $product = Product::where('name', 'Serum kiểm thử')->firstOrFail();
        $this->assertSame($this->vendor->id, $product->vendor_id);
        $this->assertSame('draft', $product->status);
        $this->assertNull($product->moderated_by);
        $this->assertSame('S5-NEW-SKU', $product->skus->sole()->sku_code);
    }

    public function test_edit_preserves_moderation_and_reorders_ingredients_without_losing_concentration(): void
    {
        $first = Ingredient::create(['inci_name' => 'Niacinamide', 'display_name' => 'Vitamin B3']);
        $second = Ingredient::create(['inci_name' => 'Glycerin', 'display_name' => 'Glycerin']);
        $this->product->ingredients()->attach([$first->id => ['position' => 1, 'concentration' => '5.00'], $second->id => ['position' => 2, 'concentration' => null]]);
        $this->product->update(['status' => 'approved']);
        $payload = $this->payload();
        $payload['ingredients'] = [['ingredient_id' => $second->id, 'position' => 1], ['ingredient_id' => $first->id, 'position' => 2, 'concentration' => 99]];
        $payload['status'] = 'draft';
        $this->put(route('vendor.products.update', $this->product), $payload)->assertRedirect();
        $fresh = $this->product->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame([$second->id, $first->id], $fresh->ingredients->pluck('id')->all());
        $this->assertEquals(5, $fresh->ingredients->last()->pivot->concentration);
        $this->assertSame(120000, $this->sku->fresh()->price);
    }

    public function test_foreign_sku_and_duplicate_sku_code_do_not_partially_save_product(): void
    {
        $foreign = Sku::factory()->create();
        $payload = $this->payload();
        $payload['skus'][0]['id'] = $foreign->id;
        $this->putJson(route('vendor.products.update', $this->product), $payload)->assertUnprocessable();
        $payload['skus'][0]['id'] = $this->sku->id;
        $payload['skus'][0]['sku_code'] = $foreign->sku_code;
        $this->putJson(route('vendor.products.update', $this->product), $payload)->assertUnprocessable();
        $this->assertNotSame('Serum kiểm thử', $this->product->fresh()->name);
        $this->assertSame(100000, $this->sku->fresh()->price);
    }

    public function test_used_sku_cannot_be_removed_or_product_deleted(): void
    {
        $batch = $this->batch();
        $payload = $this->payload();
        unset($payload['skus'][0]['id']);
        $payload['skus'][0]['sku_code'] = 'REPLACEMENT-S5';
        $this->putJson(route('vendor.products.update', $this->product), $payload)->assertUnprocessable();
        $this->deleteJson(route('vendor.products.destroy', $this->product))->assertUnprocessable();
        $this->assertDatabaseHas('skus', ['id' => $this->sku->id]);
        $this->assertDatabaseHas('batches', ['id' => $batch->id, 'qty_on_hand' => 10]);
    }

    public function test_other_vendor_cannot_edit_product_or_adjust_batch(): void
    {
        $batch = $this->batch();
        $this->actingAs(Vendor::factory()->approved()->create()->owner);
        $this->putJson(route('vendor.products.update', $this->product), $this->payload())->assertNotFound();
        $this->postJson(route('vendor.inventory.adjust', $batch), ['delta' => -1, 'reason' => 'Không có quyền'])->assertForbidden();
        $this->assertSame(10, $batch->fresh()->qty_on_hand);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_adjust_boundary_audits_signed_delta_and_rejects_reserved_stock_loss(): void
    {
        $batch = $this->batch();
        $batch->update(['qty_reserved' => 4]);
        $url = route('vendor.inventory.adjust', $batch);
        $this->postJson($url, ['delta' => -6, 'reason' => 'Kiểm kê'])->assertRedirect();
        $this->assertDatabaseHas('stock_movements', ['batch_id' => $batch->id, 'type' => 'adjust', 'qty' => -6, 'reason' => 'Kiểm kê', 'created_by' => $this->vendor->owner_user_id]);
        $this->postJson($url, ['delta' => -1, 'reason' => 'Vượt giữ chỗ'])->assertUnprocessable();
        $this->postJson($url, ['delta' => 1, 'reason' => '  '])->assertUnprocessable();
        $this->assertSame(4, $batch->fresh()->qty_on_hand);
        $this->assertSame(4, $batch->fresh()->qty_reserved);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public static function unsellableHeldBatches(): array
    {
        return [
            'active expires today in Vietnam' => ['active', '2026-11-24'],
            'expired status with future date' => ['expired', '2026-12-31'],
            'quarantined' => ['quarantined', '2026-12-31'],
        ];
    }

    #[DataProvider('unsellableHeldBatches')]
    public function test_shipping_revalidates_held_batch_but_release_remains_possible(string $status, string $expiry): void
    {
        $batch = $this->batch();
        $order = Order::create([
            'order_code' => 'S5-'.Str::random(10), 'customer_id' => User::factory()->customer()->create()->id,
            'receiver' => 'Test', 'phone' => '0900000000', 'address_text' => 'Test',
            'subtotal' => 100000, 'grand_total' => 100000, 'idempotency_key' => (string) Str::uuid(),
        ]);
        $vo = VendorOrder::create(['order_id' => $order->id, 'vendor_id' => $this->vendor->id, 'vo_code' => 'S5-VO-'.Str::random(10), 'status' => 'packed', 'subtotal' => 100000, 'total' => 100000]);
        $item = $vo->items()->create(['sku_id' => $this->sku->id, 'product_name' => $this->product->name, 'sku_code' => $this->sku->sku_code, 'unit_price' => 100000, 'qty' => 1, 'line_total' => 100000, 'commission_rate' => 10, 'commission_amount' => 10000]);
        app(ReservationService::class)->reserve($item, $this->sku, 1, $this->vendor->owner_user_id);
        $batch->refresh()->update(['status' => $status, 'exp_date' => $expiry]);
        try {
            app(OrderStatusService::class)->transition($vo, 'shipped', $this->vendor->owner);
            $this->fail('Shipping must reject batches that became unsellable after reservation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame('packed', $vo->fresh()->status);
        $this->assertNull($vo->fresh()->shipped_at);
        $this->assertSame(10, $batch->fresh()->qty_on_hand);
        $this->assertSame(1, $batch->fresh()->qty_reserved);
        $this->assertDatabaseCount('order_status_history', 0);
        $this->assertDatabaseMissing('stock_movements', ['type' => 'commit']);
        app(InventoryService::class)->releaseForVendorOrder($vo, $this->vendor->owner_user_id);
        $this->assertSame(0, $batch->fresh()->qty_reserved);
        $this->assertSame('released', $item->allocations()->sole()->status);
    }
}
