<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * UC04 integration tests for ACTUAL Laravel schema (products/skus/batches).
 * These deterministic fixtures intentionally DO NOT depend on DatabaseSeeder.
 * The suite uses the project's test DB (usually in-memory SQLite), never MySQL.
 */
final class Uc04ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_browse_catalog_with_stable_pagination(): void
    {
        $this->createProduct('Peach Balm');
        $this->createProduct('Rose Serum');
        $this->createProduct('Green Toner');

        $first = $this->getJson('/api/catalog/products?sort=price_asc&per_page=2');
        $first->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total', 'last_page'])
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('total', 3);
        $this->assertCount(2, $first->json('data'));

        $second = $this->getJson('/api/catalog/products?sort=price_asc&per_page=2&page=2');
        $second->assertOk()->assertJsonPath('total', 3);
        $this->assertCount(1, $second->json('data'));
        $ids = array_merge(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id'));
        $this->assertCount(3, array_unique($ids));
    }

    public function test_invalid_filters_are_rejected_with_422(): void
    {
        $cases = [
            'sort=unsupported' => 'sort',
            'per_page=49' => 'per_page',
            'page=-1' => 'page',
            'category_id=abc' => 'category_id',
            'brand_id=-3' => 'brand_id',
            'in_stock=banana' => 'in_stock',
            'min_price=500000&max_price=100000' => 'max_price',
            'min_price=-1' => 'min_price',
        ];
        foreach ($cases as $params => $field) {
            $this->getJson('/api/catalog/products?'.$params)
                ->assertStatus(422)
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_name_brand_and_description_are_searchable_for_guest(): void
    {
        $row = $this->createProduct('Hydrating Cream', ['brand_name' => 'CeraVe', 'description' => 'For gentle cleansing']);
        $other = $this->createProduct('Mascara');
        foreach (['Hydrating', 'CeraVe', 'cleansing'] as $keyword) {
            $response = $this->getJson('/api/catalog/products?q='.rawurlencode($keyword));
            $response->assertOk();
            $this->assertSame([(int) $row['product_id']], $this->ids($response->json('data')));
            $this->assertNotContains((int) $other['product_id'], $this->ids($response->json('data')));
        }
    }

    public function test_draft_pending_rejected_and_archived_products_are_hidden(): void
    {
        foreach (['draft', 'pending', 'rejected', 'archived'] as $state) {
            $this->createProduct('Hidden '.$state, ['status' => $state]);
        }
        $published = $this->createProduct('Visible Cream');
        $response = $this->getJson('/api/catalog/products?per_page=48');
        $response->assertOk()->assertJsonPath('total', 1);
        $this->assertSame([(int) $published['product_id']], $this->ids($response->json('data')));
    }

    public function test_unapproved_and_suspended_vendors_do_not_expose_products(): void
    {
        foreach (['pending', 'rejected', 'suspended'] as $status) {
            $this->createProduct('Hidden '.$status, ['vendor_status' => $status]);
        }
        $visible = $this->createProduct('Approved Store Product');
        $response = $this->getJson('/api/catalog/products');
        $response->assertOk()->assertJsonPath('total', 1);
        $this->assertSame([(int) $visible['product_id']], $this->ids($response->json('data')));

        // Changing vendor status must immediately hide its previously public SKU.
        DB::table('vendors')->where('id', $visible['vendor_id'])->update(['status' => 'suspended']);
        $this->getJson('/api/catalog/products')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_inactive_sku_or_product_without_sku_is_not_listed(): void
    {
        $this->createProduct('Disabled SKU', ['sku_active' => false]);
        $none = $this->createProduct('No SKU');
        DB::table('batches')->where('sku_id', $none['sku_id'])->delete();
        DB::table('skus')->where('id', $none['sku_id'])->delete();

        $visible = $this->createProduct('Active SKU');
        $response = $this->getJson('/api/catalog/products');
        $response->assertOk()->assertJsonPath('total', 1);
        $this->assertSame([(int) $visible['product_id']], $this->ids($response->json('data')));
    }

    public function test_stock_filter_uses_unreserved_stock_active_batches_and_expiry(): void
    {
        $soldOut = $this->createProduct('Sold Out', ['stock' => 0]);
        $reserved = $this->createProduct('Fully Reserved', ['stock' => 3, 'reserved' => 3]);
        $expired = $this->createProduct('Expired Cream', ['expiry' => '-1 day']);
        $blocked = $this->createProduct('Recalled Batch', ['batch_status' => 'recalled']);
        $ready = $this->createProduct('Ready To Ship', ['stock' => 5, 'reserved' => 2]);

        $all = $this->getJson('/api/catalog/products');
        $all->assertOk()->assertJsonPath('total', 5);
        $this->assertContains((int) $soldOut['product_id'], $this->ids($all->json('data')));

        $filtered = $this->getJson('/api/catalog/products?in_stock=1');
        $filtered->assertOk()->assertJsonPath('total', 1);
        $this->assertSame([(int) $ready['product_id']], $this->ids($filtered->json('data')));
        foreach ([$reserved, $expired, $blocked] as $hidden) {
            $this->assertNotContains((int) $hidden['product_id'], $this->ids($filtered->json('data')));
        }
    }

    public function test_price_filter_uses_active_sku_prices_and_does_not_duplicate_products(): void
    {
        $product = $this->createProduct('Two Sizes', ['price' => 100000]);
        $this->addSku($product['product_id'], 450000);
        $this->createProduct('Other Item', ['price' => 250000]);

        $result = $this->getJson('/api/catalog/products?min_price=400000&max_price=500000');
        $result->assertOk()->assertJsonPath('total', 1);
        $this->assertSame((int) $product['product_id'], (int) $result->json('data.0.id'));
        $this->assertSame(450000, (int) $result->json('data.0.price'));

        $base = $this->getJson('/api/catalog/products?q=Two%20Sizes');
        $base->assertOk()->assertJsonPath('total', 1);
        $this->assertSame(100000, (int) $base->json('data.0.price'));
    }

    public function test_prices_sort_ascending_descending_and_tie_break_by_id(): void
    {
        $a = $this->createProduct('First', ['price' => 200000]);
        $b = $this->createProduct('Second', ['price' => 100000]);
        $c = $this->createProduct('Third', ['price' => 200000]);

        $asc = $this->getJson('/api/catalog/products?sort=price_asc');
        $asc->assertOk();
        $this->assertSame([(int) $b['product_id'], (int) $a['product_id'], (int) $c['product_id']], $this->ids($asc->json('data')));

        $desc = $this->getJson('/api/catalog/products?sort=price_desc');
        $desc->assertOk();
        $this->assertSame([(int) $a['product_id'], (int) $c['product_id'], (int) $b['product_id']], $this->ids($desc->json('data')));
    }

    public function test_category_filter_can_include_descendants(): void
    {
        $parent = $this->createCategory('Skin care');
        $child = $this->createCategory('Cleansers', $parent);
        $one = $this->createProduct('Parent Product', ['category_id' => $parent]);
        $two = $this->createProduct('Child Product', ['category_id' => $child]);
        $this->createProduct('Other Category');

        $base = '/api/catalog/products?category_id='.$parent;
        $this->getJson($base.'&include_descendants=0')->assertOk()->assertJsonPath('total', 1);
        $all = $this->getJson($base.'&include_descendants=1');
        $all->assertOk()->assertJsonPath('total', 2);
        $this->assertEqualsCanonicalizing([(int) $one['product_id'], (int) $two['product_id']], $this->ids($all->json('data')));
    }

    public function test_filter_by_brand_and_allow_nullable_product_brand(): void
    {
        $one = $this->createProduct('Brand Alpha Product', ['brand_name' => 'Brand Alpha']);
        $this->createProduct('Brand Beta Product', ['brand_name' => 'Brand Beta']);
        $none = $this->createProduct('Unbranded Product', ['brand_name' => null]);

        $filtered = $this->getJson('/api/catalog/products?brand_id='.$one['brand_id']);
        $filtered->assertOk()->assertJsonPath('total', 1);
        $this->assertSame([(int) $one['product_id']], $this->ids($filtered->json('data')));

        $noBrand = $this->getJson('/api/catalog/products?q=Unbranded');
        $noBrand->assertOk()->assertJsonPath('total', 1);
        $this->assertSame((int) $none['product_id'], (int) $noBrand->json('data.0.id'));
        $this->assertNull($noBrand->json('data.0.brand_name'));
    }

    public function test_like_wildcards_are_literal_and_sql_input_cannot_bypass_visibility(): void
    {
        $percent = $this->createProduct('Glow 50%');
        $this->createProduct('Ordinary Serum');
        $this->createProduct('Hidden 100%', ['status' => 'draft']);

        $match = $this->getJson('/api/catalog/products?q=%25');
        $match->assertOk()->assertJsonPath('total', 1);
        $this->assertSame([(int) $percent['product_id']], $this->ids($match->json('data')));
        $this->getJson('/api/catalog/products?q=%5F')->assertOk()->assertJsonPath('total', 0);
        $payload = $this->getJson('/api/catalog/products?q='.rawurlencode("' OR 1=1 --"));
        $payload->assertOk()->assertJsonPath('total', 0);
    }

    public function test_image_selection_uses_lowest_sort_order(): void
    {
        $p = $this->createProduct('With Images');
        DB::table('product_images')->insert([
            ['product_id' => $p['product_id'], 'path' => 'products/second.jpg', 'sort' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $p['product_id'], 'path' => 'products/first.jpg', 'sort' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->getJson('/api/catalog/products?q=With%20Images')
            ->assertOk()
            ->assertJsonPath('data.0.image_path', 'products/first.jpg');
    }

    /** Insert a minimum complete, sellable product graph for the REAL schema. */
    private function createProduct(string $name, array $options = []): array
    {
        $owner = User::factory()->create();
        $vendorId = DB::table('vendors')->insertGetId([
            'owner_user_id' => $owner->id,
            'store_name' => 'Test Shop '.Str::random(10),
            'tax_code' => Str::random(12),
            'license_file' => 'testing/license.pdf',
            'status' => $options['vendor_status'] ?? 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $brandName = array_key_exists('brand_name', $options) ? $options['brand_name'] : 'Test Brand';
        $brandId = $brandName === null ? null : DB::table('brands')->insertGetId([
            'name' => $brandName.' '.Str::random(6),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $categoryId = $options['category_id'] ?? $this->createCategory('Beauty');
        $productId = DB::table('products')->insertGetId([
            'vendor_id' => $vendorId,
            'brand_id' => $brandId,
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
            'description' => $options['description'] ?? 'A testing product',
            'status' => $options['status'] ?? 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $skuId = $this->addSku($productId, $options['price'] ?? 200000, $options);

        return ['vendor_id' => $vendorId, 'brand_id' => $brandId, 'category_id' => $categoryId, 'product_id' => $productId, 'sku_id' => $skuId];
    }

    private function addSku(int $productId, int $price, array $options = []): int
    {
        $skuId = DB::table('skus')->insertGetId([
            'product_id' => $productId,
            'sku_code' => 'TEST-'.Str::upper(Str::random(12)),
            'variant_name' => 'Default size',
            'price' => $price,
            'is_active' => $options['sku_active'] ?? true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('batches')->insert([
            'sku_id' => $skuId,
            'batch_no' => 'LOT-'.Str::upper(Str::random(10)),
            'mfg_date' => now()->subMonths(2)->toDateString(),
            'exp_date' => now()->modify($options['expiry'] ?? '+6 months')->toDateString(),
            'qty_received' => 20,
            'qty_on_hand' => $options['stock'] ?? 10,
            'qty_reserved' => $options['reserved'] ?? 0,
            'status' => $options['batch_status'] ?? 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $skuId;
    }

    private function createCategory(string $name, ?int $parentId = null): int
    {
        return DB::table('categories')->insertGetId([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
            'parent_id' => $parentId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return int[] */
    private function ids(array $data): array
    {
        return array_map('intval', array_column($data, 'id'));
    }
}
