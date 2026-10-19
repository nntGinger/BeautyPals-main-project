<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Batch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SkinType;
use App\Models\Sku;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed 3 Users (Admin, Vendor, Customer)
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'phone' => '0900000001',
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $vendorUser = User::firstOrCreate(
            ['email' => 'vendor@example.com'],
            [
                'name' => 'Vendor Demo',
                'password' => Hash::make('password'),
                'phone' => '0900000002',
                'role' => 'vendor',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $customer = User::firstOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Customer Demo',
                'password' => Hash::make('password'),
                'phone' => '0900000003',
                'role' => 'customer',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // 2. Seed Customer Address
        Address::firstOrCreate(
            ['user_id' => $customer->id, 'line' => '123 Nguyen Hue'],
            [
                'receiver' => 'Customer Demo',
                'phone' => '0900000003',
                'ward' => 'Ben Nghe',
                'district' => 'Quan 1',
                'city' => 'TP. Ho Chi Minh',
                'is_default' => true,
            ]
        );

        // 3. Seed Vendor (Approved)
        $vendor = Vendor::firstOrCreate(
            ['owner_user_id' => $vendorUser->id],
            [
                'store_name' => 'Beauty Pals Official Store',
                'tax_code' => '0312345678',
                'phone' => '0900000002',
                'address' => '456 Le Duan, Ben Nghe, Quan 1, TP. Ho Chi Minh',
                'license_file' => 'licenses/business_license_demo.pdf',
                'status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]
        );

        // 4. Seed Brands
        $brand = Brand::firstOrCreate(['name' => 'Innisfree']);
        Brand::firstOrCreate(['name' => 'La Roche-Posay']);

        // 5. Seed Categories
        $parentCat = Category::firstOrCreate(
            ['slug' => 'cham-soc-da-mat'],
            ['name' => 'Chăm sóc da mặt']
        );

        $subCat = Category::firstOrCreate(
            ['slug' => 'kem-duong-am'],
            [
                'name' => 'Kem dưỡng ẩm',
                'parent_id' => $parentCat->id,
            ]
        );

        // 6. Seed Skin Types
        $skinTypeOily = SkinType::firstOrCreate(['name' => 'Da dầu']);
        $skinTypeDry = SkinType::firstOrCreate(['name' => 'Da khô']);
        SkinType::firstOrCreate(['name' => 'Da nhạy cảm']);
        SkinType::firstOrCreate(['name' => 'Da hỗn hợp']);

        // 7. Seed Product (Approved)
        $product = Product::firstOrCreate(
            ['slug' => 'kem-duong-am-green-tea-seed-hyaluronic-cream-50ml'],
            [
                'vendor_id' => $vendor->id,
                'brand_id' => $brand->id,
                'category_id' => $subCat->id,
                'name' => 'Kem Dưỡng Ẩm Green Tea Seed Hyaluronic Cream',
                'description' => 'Kem dưỡng ẩm chuyên sâu chiết xuất từ trà xanh đảo Jeju và Axit Hyaluronic giúp cấp ẩm, phục hồi hàng rào bảo vệ da.',
                'status' => 'approved',
                'moderated_by' => $admin->id,
                'moderated_at' => now(),
            ]
        );

        $product->skinTypes()->syncWithoutDetaching([$skinTypeOily->id, $skinTypeDry->id]);

        ProductImage::firstOrCreate(
            ['product_id' => $product->id, 'sort' => 1],
            ['path' => 'products/innisfree_green_tea_cream_1.jpg']
        );

        // 8. Seed SKU
        $sku = Sku::firstOrCreate(
            ['sku_code' => 'INN-GTC-50ML'],
            [
                'product_id' => $product->id,
                'variant_name' => 'Hũ 50ml',
                'price' => 450000,
                'size' => '50ml',
                'is_active' => true,
            ]
        );

        // 9. Seed Batches (Lô hàng còn hạn)
        Batch::firstOrCreate(
            ['sku_id' => $sku->id, 'batch_no' => 'LOT-2026-001'],
            [
                'mfg_date' => now()->subMonths(3)->toDateString(),
                'exp_date' => now()->addMonths(21)->toDateString(),
                'qty_received' => 100,
                'qty_on_hand' => 95,
                'qty_reserved' => 5,
                'status' => 'active',
            ]
        );
    }
}
