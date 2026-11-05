<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientAlias;
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

        // 4. Ingredients and Aliases
        $ingredients = [
            [
                'inci' => 'Niacinamide',
                'name' => 'Niacinamide',
                'function' => 'Làm sáng da, kiềm dầu, phục hồi màng ẩm',
                'aliases' => ['Vitamin B3', 'Nicotinamide'],
            ],
            [
                'inci' => 'Sodium Hyaluronate',
                'name' => 'Hyaluronic Acid',
                'function' => 'Cấp nước sâu, căng mọng da đa tầng',
                'aliases' => ['HA', 'Sodium Hyaluronate', 'Axit Hyaluronic'],
            ],
            [
                'inci' => 'Salicylic Acid',
                'name' => 'Salicylic Acid (BHA)',
                'function' => 'Tẩy da chết lỗ chân lông, ngừa và giảm mụn',
                'aliases' => ['BHA', 'Axit Salicylic'],
            ],
            [
                'inci' => 'Centella Asiatica Extract',
                'name' => 'Chiết xuất rau má (Centella)',
                'function' => 'Làm dịu da kích ứng, kháng viêm, phục hồi',
                'aliases' => ['Cica', 'Chiết xuất rau má', 'Rau má Jeju'],
            ],
            [
                'inci' => 'Ceramide NP',
                'name' => 'Ceramide NP',
                'function' => 'Củng cố màng lipid, khóa ẩm và bảo vệ da',
                'aliases' => ['Ceramide 3', 'Phytoceramide'],
            ],
            [
                'inci' => 'Zinc PCA',
                'name' => 'Kẽm PCA (Zinc PCA)',
                'function' => 'Kiểm soát bã nhờn, kháng khuẩn mụn',
                'aliases' => ['Kẽm PCA', 'Zinc Salt'],
            ],
            [
                'inci' => 'Retinol',
                'name' => 'Retinol',
                'function' => 'Kích thích tăng sinh collagen, chống lão hóa',
                'aliases' => ['Vitamin A', 'Pure Retinol'],
            ],
            [
                'inci' => 'Camellia Sinensis Leaf Extract',
                'name' => 'Chiết xuất lá trà xanh',
                'function' => 'Chống oxy hóa mạnh mẽ, thanh lọc và làm mát da',
                'aliases' => ['Trà xanh Jeju', 'Green Tea Extract'],
            ],
            [
                'inci' => 'Panthenol',
                'name' => 'Panthenol (Vitamin B5)',
                'function' => 'Dưỡng ẩm, làm dịu da cháy nắng hoặc tổn thương',
                'aliases' => ['Vitamin B5', 'Pro-Vitamin B5', 'D-Panthenol'],
            ],
            [
                'inci' => 'Tocopherol',
                'name' => 'Vitamin E (Tocopherol)',
                'function' => 'Chống gốc tự do, nuôi dưỡng làn da mịn màng',
                'aliases' => ['Vitamin E', 'D-alpha-Tocopherol'],
            ],
        ];

        foreach ($ingredients as $item) {
            $ing = Ingredient::query()->firstOrCreate(
                ['inci_name' => $item['inci']],
                [
                    'display_name' => $item['name'],
                    'function' => $item['function'],
                ],
            );

            foreach ($item['aliases'] as $alias) {
                IngredientAlias::query()->firstOrCreate(
                    ['alias' => $alias],
                    ['ingredient_id' => $ing->id],
                );
            }
        }
    }
}
