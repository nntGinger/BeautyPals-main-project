<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Sku;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAndBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_crud_categories_and_brands_while_others_get_403(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $vendorUser = Vendor::factory()->approved()->create()->owner;

        $this->actingAs($customer)->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($vendorUser)->post(route('admin.brands.store'), ['name' => 'Brand X'])->assertForbidden();

        $this->actingAs($admin);

        // Create Category
        $this->post(route('admin.categories.store'), [
            'name' => 'Chăm sóc chuyên sâu',
            'slug' => '',
        ])->assertRedirect(route('admin.categories.index'));

        $category = Category::where('name', 'Chăm sóc chuyên sâu')->firstOrFail();
        $this->assertSame('cham-soc-chuyen-sau', $category->slug);

        // Duplicate Category name rejected
        $this->post(route('admin.categories.store'), [
            'name' => 'Chăm sóc chuyên sâu',
        ])->assertSessionHasErrors('name');

        // Create Brand
        $this->post(route('admin.brands.store'), [
            'name' => 'Sulwhasoo',
        ])->assertRedirect(route('admin.brands.index'));

        $brand = Brand::where('name', 'Sulwhasoo')->firstOrFail();

        // Duplicate Brand name rejected
        $this->post(route('admin.brands.store'), [
            'name' => 'Sulwhasoo',
        ])->assertSessionHasErrors('name');

        // Update Brand
        $this->put(route('admin.brands.update', $brand), [
            'name' => 'Sulwhasoo Korea',
        ])->assertRedirect(route('admin.brands.index'));

        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'name' => 'Sulwhasoo Korea']);
    }

    public function test_product_scopes_and_catalog_filter_by_ingredient_alias_and_only_show_approved(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::factory()->create(['name' => 'Serum', 'slug' => 'serum']);
        $brand = Brand::factory()->create(['name' => 'The Ordinary']);

        $niacinamide = Ingredient::create([
            'inci_name' => 'Niacinamide',
            'display_name' => 'Niacinamide',
        ]);
        $niacinamide->aliases()->createMany([
            ['alias' => 'Vitamin B3'],
            ['alias' => 'Nicotinamide'],
        ]);

        $retinol = Ingredient::create([
            'inci_name' => 'Retinol',
            'display_name' => 'Vitamin A',
        ]);

        $approvedB3 = Product::factory()->approved()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Serum Dưỡng Sáng B3 10%',
            'slug' => 'serum-duong-sang-b3-10',
        ]);
        $approvedB3->ingredients()->attach($niacinamide->id, ['position' => 1]);
        $skuB3 = Sku::factory()->create([
            'product_id' => $approvedB3->id,
            'sku_code' => 'SKU-B3-30ML',
            'price' => 250000,
            'is_active' => true,
        ]);
        Batch::factory()->create([
            'sku_id' => $skuB3->id,
            'qty_received' => 30,
            'qty_on_hand' => 30,
            'exp_date' => now()->addMonths(8)->toDateString(),
        ]);

        $approvedRetinol = Product::factory()->approved()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Kem Đêm Retinol 0.5%',
            'slug' => 'kem-dem-retinol-05',
        ]);
        $approvedRetinol->ingredients()->attach($retinol->id, ['position' => 1]);
        Sku::factory()->create([
            'product_id' => $approvedRetinol->id,
            'sku_code' => 'SKU-RET-30ML',
            'price' => 550000,
            'is_active' => true,
        ]);

        $draftProduct = Product::factory()->draft()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Sản phẩm Nháp Ẩn',
            'slug' => 'san-pham-nhap-an',
        ]);

        // Filter by alias "Vitamin B3" finds Niacinamide product and never shows draft product
        $this->get(route('catalog.index', ['with_ingredient' => 'Vitamin B3']))
            ->assertOk()
            ->assertSee('Serum Dưỡng Sáng B3 10%')
            ->assertDontSee('Kem Đêm Retinol 0.5%')
            ->assertDontSee('Sản phẩm Nháp Ẩn');

        // Filter excluding Retinol
        $this->get(route('catalog.index', ['without_ingredient' => 'Retinol']))
            ->assertOk()
            ->assertSee('Serum Dưỡng Sáng B3 10%')
            ->assertDontSee('Kem Đêm Retinol 0.5%');

        // Draft product detail returns 404
        $this->get(route('catalog.show', $draftProduct->slug))->assertNotFound();

        // Approved product detail shows SKU, ingredient, and nearest expiry
        $this->get(route('catalog.show', $approvedB3->slug))
            ->assertOk()
            ->assertSee('Serum Dưỡng Sáng B3 10%')
            ->assertSee('Niacinamide')
            ->assertSee('SKU-B3-30ML');
    }

    public function test_vendor_batch_receiving_validates_dates_quantity_uniqueness_and_ownership(): void
    {
        $vendorA = Vendor::factory()->approved()->create();
        $vendorB = Vendor::factory()->approved()->create();

        $productA = Product::factory()->approved()->create(['vendor_id' => $vendorA->id]);
        $skuA = Sku::factory()->create(['product_id' => $productA->id, 'sku_code' => 'SKU-VA-01']);

        // 1. Vendor B cannot receive batch into Vendor A's SKU
        $this->actingAs($vendorB->owner)
            ->post(route('vendor.inventory.receive'), [
                'sku_id' => $skuA->id,
                'batch_no' => 'LOT-HACK-01',
                'mfg_date' => now()->subMonth()->toDateString(),
                'exp_date' => now()->addYear()->toDateString(),
                'qty' => 50,
            ])
            ->assertForbidden();

        $this->actingAs($vendorA->owner);

        // 2. Reject expired date or exp_date <= mfg_date
        $this->post(route('vendor.inventory.receive'), [
            'sku_id' => $skuA->id,
            'batch_no' => 'LOT-BAD-DATE',
            'mfg_date' => now()->subMonths(6)->toDateString(),
            'exp_date' => now()->subDay()->toDateString(),
            'qty' => 20,
        ])->assertSessionHasErrors('exp_date');

        // 3. Reject negative or zero quantity
        $this->post(route('vendor.inventory.receive'), [
            'sku_id' => $skuA->id,
            'batch_no' => 'LOT-ZERO',
            'mfg_date' => now()->subMonth()->toDateString(),
            'exp_date' => now()->addYear()->toDateString(),
            'qty' => 0,
        ])->assertSessionHasErrors('qty');

        // 4. Valid batch receive creates Batch and StockMovement
        $this->post(route('vendor.inventory.receive'), [
            'sku_id' => $skuA->id,
            'batch_no' => 'LOT-VA-001',
            'mfg_date' => now()->subMonths(2)->toDateString(),
            'exp_date' => now()->addMonths(10)->toDateString(),
            'qty' => 80,
        ])->assertRedirect(route('vendor.inventory.index'));

        // 5. Reject duplicate batch_no within the same SKU
        $this->post(route('vendor.inventory.receive'), [
            'sku_id' => $skuA->id,
            'batch_no' => 'LOT-VA-001',
            'mfg_date' => now()->subMonth()->toDateString(),
            'exp_date' => now()->addYear()->toDateString(),
            'qty' => 40,
        ])->assertSessionHasErrors('batch_no');

        // Second valid batch
        $this->post(route('vendor.inventory.receive'), [
            'sku_id' => $skuA->id,
            'batch_no' => 'LOT-VA-002',
            'mfg_date' => now()->subMonth()->toDateString(),
            'exp_date' => now()->addDays(30)->toDateString(),
            'qty' => 45,
        ])->assertRedirect(route('vendor.inventory.index'));

        $totalOnHand = (int) Batch::where('sku_id', $skuA->id)->sum('qty_on_hand');
        $totalMovements = (int) StockMovement::whereHas('batch', fn ($q) => $q->where('sku_id', $skuA->id))->sum('qty');

        $this->assertSame(125, $totalOnHand);
        $this->assertSame(125, $totalMovements);
    }
}
