<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\SkinType;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Brands
        $brands = [
            'Innisfree',
            'La Roche-Posay',
            'CeraVe',
            'The Ordinary',
            'Paula\'s Choice',
            'Anessa',
            'Klairs',
            'MAC',
        ];

        foreach ($brands as $name) {
            Brand::query()->firstOrCreate(['name' => $name]);
        }

        // 2. Hierarchical Categories
        $faceCare = Category::query()->firstOrCreate(
            ['slug' => 'cham-soc-da-mat'],
            ['name' => 'Chăm sóc da mặt', 'parent_id' => null],
        );

        $makeup = Category::query()->firstOrCreate(
            ['slug' => 'trang-diem'],
            ['name' => 'Trang điểm', 'parent_id' => null],
        );

        $bodyCare = Category::query()->firstOrCreate(
            ['slug' => 'cham-soc-co-the'],
            ['name' => 'Chăm sóc cơ thể', 'parent_id' => null],
        );

        $subCategories = [
            ['name' => 'Sữa rửa mặt', 'slug' => 'sua-rua-mat', 'parent_id' => $faceCare->id],
            ['name' => 'Tẩy trang', 'slug' => 'tay-trang', 'parent_id' => $faceCare->id],
            ['name' => 'Toner & Nước cân bằng', 'slug' => 'toner-nuoc-can-bang', 'parent_id' => $faceCare->id],
            ['name' => 'Serum & Tinh chất', 'slug' => 'serum-tinh-chat', 'parent_id' => $faceCare->id],
            ['name' => 'Kem dưỡng ẩm', 'slug' => 'kem-duong-am', 'parent_id' => $faceCare->id],
            ['name' => 'Kem chống nắng', 'slug' => 'kem-chong-nang', 'parent_id' => $faceCare->id],
            ['name' => 'Son môi', 'slug' => 'son-moi', 'parent_id' => $makeup->id],
            ['name' => 'Phấn nền & Cushion', 'slug' => 'phan-nen-cushion', 'parent_id' => $makeup->id],
            ['name' => 'Sữa tắm & Dưỡng thể', 'slug' => 'sua-tam-duong-the', 'parent_id' => $bodyCare->id],
        ];

        foreach ($subCategories as $sub) {
            Category::query()->firstOrCreate(
                ['slug' => $sub['slug']],
                ['name' => $sub['name'], 'parent_id' => $sub['parent_id']],
            );
        }

        // 3. Skin Types
        $skinTypes = [
            'Da dầu',
            'Da khô',
            'Da nhạy cảm',
            'Da hỗn hợp',
            'Da thường',
            'Da mụn',
        ];

        foreach ($skinTypes as $name) {
            SkinType::query()->firstOrCreate(['name' => $name]);
        }
    }
}
