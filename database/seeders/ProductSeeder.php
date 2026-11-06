<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SkinType;
use App\Models\Sku;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        $vendor1 = Vendor::where('store_name', 'BeautyPals Official Store')->first();
        $vendor2 = Vendor::where('store_name', 'An Minh Cosmetics Store')->first();
        $vendor3 = Vendor::where('store_name', 'Saigon Derma Lab')->first() ?? $vendor1;

        // 1. Innisfree Cream (Vendor 1)
        $brandInnisfree = Brand::where('name', 'Innisfree')->first();
        $catMoisturizer = Category::where('slug', 'kem-duong-am')->first();

        $p1 = Product::query()->firstOrCreate(
            ['slug' => 'kem-duong-am-green-tea-seed-hyaluronic-cream'],
            [
                'vendor_id' => $vendor1->id,
                'brand_id' => $brandInnisfree?->id,
                'category_id' => $catMoisturizer->id,
                'name' => 'Kem Dưỡng Ẩm Trà Xanh Innisfree Green Tea Seed Hyaluronic Cream',
                'description' => 'Kem dưỡng ẩm chuyên sâu chiết xuất từ trà xanh đảo Jeju và Axit Hyaluronic giúp cấp ẩm, phục hồi hàng rào bảo vệ da, dưỡng da căng mọng tự nhiên.',
                'status' => 'approved',
                'moderated_by' => $admin?->id,
                'moderated_at' => now(),
            ],
        );

        $this->attachSkinTypes($p1, ['Da dầu', 'Da khô', 'Da hỗn hợp']);
        $this->attachIngredients($p1, [
            ['inci' => 'Camellia Sinensis Leaf Extract', 'pos' => 1, 'conc' => 75.00],
            ['inci' => 'Sodium Hyaluronate', 'pos' => 2, 'conc' => 5.00],
            ['inci' => 'Panthenol', 'pos' => 3, 'conc' => 2.00],
        ]);
        $this->createImages($p1, [
            ['path' => 'products/innisfree_green_tea_cream_1.jpg', 'sort' => 1],
            ['path' => 'products/innisfree_green_tea_cream_2.jpg', 'sort' => 2],
        ]);
        $this->createSkus($p1, [
            ['code' => 'INN-GTC-50ML', 'name' => 'Hũ 50ml', 'price' => 450000, 'size' => '50ml'],
            ['code' => 'INN-GTC-80ML', 'name' => 'Hũ 80ml (Tiết kiệm)', 'price' => 620000, 'size' => '80ml'],
        ]);

        // 2. La Roche-Posay Sunscreen (Vendor 1)
        $brandLRP = Brand::where('name', 'La Roche-Posay')->first();
        $catSunscreen = Category::where('slug', 'kem-chong-nang')->first();

        $p2 = Product::query()->firstOrCreate(
            ['slug' => 'kem-chong-nang-la-roche-posay-anthelios-oil-control'],
            [
                'vendor_id' => $vendor1->id,
                'brand_id' => $brandLRP?->id,
                'category_id' => $catSunscreen->id,
                'name' => 'Kem Chống Nắng La Roche-Posay Anthelios UVMune 400 Oil Control Gel-Cream 50ml',
                'description' => 'Màng lọc Mexoryl 400 bảo vệ tối ưu trước tia UVA siêu dài, công nghệ Airlicium kiểm soát dầu nhờn suốt 12 giờ, không nhờn rít, không để lại vệt trắng.',
                'status' => 'approved',
                'moderated_by' => $admin?->id,
                'moderated_at' => now(),
            ],
        );

        $this->attachSkinTypes($p2, ['Da dầu', 'Da mụn', 'Da nhạy cảm']);
        $this->attachIngredients($p2, [
            ['inci' => 'Zinc PCA', 'pos' => 1, 'conc' => 2.00],
            ['inci' => 'Niacinamide', 'pos' => 2, 'conc' => 1.50],
            ['inci' => 'Tocopherol', 'pos' => 3, 'conc' => 1.00],
        ]);
        $this->createImages($p2, [
            ['path' => 'products/lrp_anthelios_uvmune_1.jpg', 'sort' => 1],
        ]);
        $this->createSkus($p2, [
            ['code' => 'LRP-ANT-50ML', 'name' => 'Tuýp 50ml', 'price' => 485000, 'size' => '50ml'],
        ]);

        // 3. CeraVe Foaming Cleanser (Vendor 1)
        $brandCeraVe = Brand::where('name', 'CeraVe')->first();
        $catCleanser = Category::where('slug', 'sua-rua-mat')->first();

        $p3 = Product::query()->firstOrCreate(
            ['slug' => 'sua-rua-mat-cerave-foaming-cleanser'],
            [
                'vendor_id' => $vendor1->id,
                'brand_id' => $brandCeraVe?->id,
                'category_id' => $catCleanser->id,
                'name' => 'Sữa Rửa Mặt CeraVe Foaming Facial Cleanser Cho Da Dầu Mụn',
                'description' => 'Chứa 3 loại Ceramide thiết yếu, Niacinamide và Hyaluronic Acid giúp làm sạch sâu bụi bẩn, bã nhờn mà không phá vỡ màng ẩm tự nhiên của da.',
                'status' => 'approved',
                'moderated_by' => $admin?->id,
                'moderated_at' => now(),
            ],
        );

        $this->attachSkinTypes($p3, ['Da dầu', 'Da hỗn hợp', 'Da mụn']);
        $this->attachIngredients($p3, [
            ['inci' => 'Ceramide NP', 'pos' => 1, 'conc' => 2.00],
            ['inci' => 'Niacinamide', 'pos' => 2, 'conc' => 1.00],
            ['inci' => 'Sodium Hyaluronate', 'pos' => 3, 'conc' => 1.00],
        ]);
        $this->createImages($p3, [
            ['path' => 'products/cerave_foaming_cleanser_1.jpg', 'sort' => 1],
        ]);
        $this->createSkus($p3, [
            ['code' => 'CRV-FOAM-236ML', 'name' => 'Chai 236ml', 'price' => 280000, 'size' => '236ml'],
            ['code' => 'CRV-FOAM-473ML', 'name' => 'Chai lớn 473ml', 'price' => 430000, 'size' => '473ml'],
        ]);

        // 4. Draft Product for Vendor 1
        $p4 = Product::query()->firstOrCreate(
            ['slug' => 'kem-duong-phuc-hoi-rau-ma-draft'],
            [
                'vendor_id' => $vendor1->id,
                'brand_id' => $brandInnisfree?->id,
                'category_id' => $catMoisturizer->id,
                'name' => 'Kem Dưỡng Ẩm Phục Hồi Rau Má (Bản nháp)',
                'description' => 'Sản phẩm đang được biên tập nội dung trước khi gửi duyệt.',
                'status' => 'draft',
            ],
        );
        $this->createSkus($p4, [
            ['code' => 'BP-CICA-DRAFT-50ML', 'name' => 'Tuýp 50ml', 'price' => 350000, 'size' => '50ml'],
        ]);

        // 5. The Ordinary Serum (Vendor 2)
        $brandTO = Brand::where('name', 'The Ordinary')->first();
        $catSerum = Category::where('slug', 'serum-tinh-chat')->first();

        $p5 = Product::query()->firstOrCreate(
            ['slug' => 'tinh-chat-the-ordinary-niacinamide-10-zinc-1'],
            [
                'vendor_id' => $vendor2->id,
                'brand_id' => $brandTO?->id,
                'category_id' => $catSerum->id,
                'name' => 'Tinh Chất The Ordinary Niacinamide 10% + Zinc 1% Giảm Thâm Mụn',
                'description' => 'Công thức chứa nồng độ cao Niacinamide 10% kết hợp cùng muối kẽm 1% Zinc PCA giúp giảm mẩn đỏ, thu nhỏ lỗ chân lông và làm mờ thâm sau mụn hiệu quả.',
                'status' => 'approved',
                'moderated_by' => $admin?->id,
                'moderated_at' => now(),
            ],
        );

        $this->attachSkinTypes($p5, ['Da dầu', 'Da mụn', 'Da hỗn hợp']);
        $this->attachIngredients($p5, [
            ['inci' => 'Niacinamide', 'pos' => 1, 'conc' => 10.00],
            ['inci' => 'Zinc PCA', 'pos' => 2, 'conc' => 1.00],
        ]);
        $this->createImages($p5, [
            ['path' => 'products/the_ordinary_niacinamide_1.jpg', 'sort' => 1],
        ]);
        $this->createSkus($p5, [
            ['code' => 'TO-NIA-30ML', 'name' => 'Lọ 30ml', 'price' => 195000, 'size' => '30ml'],
            ['code' => 'TO-NIA-60ML', 'name' => 'Lọ 60ml', 'price' => 340000, 'size' => '60ml'],
        ]);

        // 6. Klairs Toner (Vendor 2)
        $brandKlairs = Brand::where('name', 'Klairs')->first();
        $catToner = Category::where('slug', 'toner-nuoc-can-bang')->first();

        $p6 = Product::query()->firstOrCreate(
            ['slug' => 'nuoc-hoa-hong-klairs-supple-preparation-unscented'],
            [
                'vendor_id' => $vendor2->id,
                'brand_id' => $brandKlairs?->id,
                'category_id' => $catToner->id,
                'name' => 'Nước Hoa Hồng Klairs Supple Preparation Unscented Toner Không Mùi',
                'description' => 'Nước hoa hồng không mùi, không cồn, không tinh dầu, cấp ẩm tức thì và cân bằng độ pH lý tưởng cho làn da nhạy cảm nhất.',
                'status' => 'approved',
                'moderated_by' => $admin?->id,
                'moderated_at' => now(),
            ],
        );

        $this->attachSkinTypes($p6, ['Da nhạy cảm', 'Da khô', 'Da thường']);
        $this->attachIngredients($p6, [
            ['inci' => 'Sodium Hyaluronate', 'pos' => 1, 'conc' => 5.00],
            ['inci' => 'Centella Asiatica Extract', 'pos' => 2, 'conc' => 3.00],
            ['inci' => 'Panthenol', 'pos' => 3, 'conc' => 1.50],
        ]);
        $this->createImages($p6, [
            ['path' => 'products/klairs_supple_toner_1.jpg', 'sort' => 1],
        ]);
        $this->createSkus($p6, [
            ['code' => 'KLA-TON-180ML', 'name' => 'Chai 180ml', 'price' => 310000, 'size' => '180ml'],
        ]);

        // 7. MAC Lipstick (Vendor 2)
        $brandMAC = Brand::where('name', 'MAC')->first();
        $catLipstick = Category::where('slug', 'son-moi')->first();

        $p7 = Product::query()->firstOrCreate(
            ['slug' => 'son-thoi-mac-matte-lipstick-chili'],
            [
                'vendor_id' => $vendor2->id,
                'brand_id' => $brandMAC?->id,
                'category_id' => $catLipstick->id,
                'name' => 'Son Thỏi MAC Matte Lipstick - Màu Chili Đỏ Gạch',
                'description' => 'Màu đỏ gạch thời thượng tôn mọi sắc tố da, chất son lì mịn mượt như nhung, chuẩn màu sau một lần quẹt và bền màu suốt cả ngày.',
                'status' => 'approved',
                'moderated_by' => $admin?->id,
                'moderated_at' => now(),
            ],
        );

        $this->attachSkinTypes($p7, ['Da thường']);
        $this->createImages($p7, [
            ['path' => 'products/mac_chili_lipstick_1.jpg', 'sort' => 1],
        ]);
        $this->createSkus($p7, [
            ['code' => 'MAC-LIP-CHILI', 'name' => 'Thỏi 3g - Màu Chili', 'price' => 610000, 'size' => '3g'],
        ]);

        // 8. Anessa Sunscreen Milk (Vendor 2)
        $brandAnessa = Brand::where('name', 'Anessa')->first();

        $p8 = Product::query()->firstOrCreate(
            ['slug' => 'kem-chong-nang-anessa-perfect-uv-skincare-milk'],
            [
                'vendor_id' => $vendor2->id,
                'brand_id' => $brandAnessa?->id,
                'category_id' => $catSunscreen->id,
                'name' => 'Sữa Chống Nắng Anessa Perfect UV Sunscreen Skincare Milk SPF50+ PA++++ 60ml',
                'description' => 'Công nghệ Auto Booster độc quyền giúp màng chống UV trở nên bền vững hơn khi tiếp xúc với Độ ẩm - Mồ hôi - Nước - Nhiệt độ cao, kết cấu dạng sữa mỏng nhẹ thấm nhanh.',
                'status' => 'approved',
                'moderated_by' => $admin?->id,
                'moderated_at' => now(),
            ],
        );

        $this->attachSkinTypes($p8, ['Da dầu', 'Da hỗn hợp', 'Da thường']);
        $this->createImages($p8, [
            ['path' => 'products/anessa_perfect_uv_milk_1.jpg', 'sort' => 1],
        ]);
        $this->createSkus($p8, [
            ['code' => 'ANE-MILK-60ML', 'name' => 'Chai 60ml', 'price' => 550000, 'size' => '60ml'],
        ]);

        // 9. Pending Product for Vendor 2 (Moderation testing)
        $p9 = Product::query()->firstOrCreate(
            ['slug' => 'serum-vitamin-c-cho-duyet'],
            [
                'vendor_id' => $vendor2->id,
                'brand_id' => $brandTO?->id,
                'category_id' => $catSerum->id,
                'name' => 'Serum Vitamin C Dưỡng Sáng Chống Lão Hóa (Chờ duyệt)',
                'description' => 'Sản phẩm mới do gian hàng An Minh đăng tải, đang chờ Admin duyệt.',
                'status' => 'pending',
            ],
        );
        $this->createSkus($p9, [
            ['code' => 'ANM-VITC-PENDING', 'name' => 'Lọ 30ml', 'price' => 420000, 'size' => '30ml'],
        ]);

        // 10..30: 21 additional approved products across 3 vendors to reach 30 products (S2-12)
        $extraCatalog = [
            ['vendor' => $vendor3, 'brand' => $brandLRP, 'cat' => $catMoisturizer, 'slug' => 'kem-duong-la-roche-posay-cicaplast-baume-b5', 'name' => 'Kem Dưỡng Phục Hồi Da La Roche-Posay Cicaplast Baume B5+', 'inci' => 'Panthenol', 'sku' => 'LRP-B5-40ML', 'price' => 385000, 'size' => '40ml'],
            ['vendor' => $vendor3, 'brand' => $brandLRP, 'cat' => $catCleanser, 'slug' => 'gel-rua-mat-la-roche-posay-effaclar', 'name' => 'Gel Rửa Mặt La Roche-Posay Effaclar Purifying Foaming Gel', 'inci' => 'Zinc PCA', 'sku' => 'LRP-EFF-200ML', 'price' => 395000, 'size' => '200ml'],
            ['vendor' => $vendor3, 'brand' => $brandCeraVe, 'cat' => $catMoisturizer, 'slug' => 'kem-duong-cerave-moisturizing-cream', 'name' => 'Kem Dưỡng Ẩm CeraVe Moisturizing Cream Phục Hồi Hàng Rào Da', 'inci' => 'Ceramide NP', 'sku' => 'CRV-CREAM-340G', 'price' => 410000, 'size' => '340g'],
            ['vendor' => $vendor3, 'brand' => $brandTO, 'cat' => $catSerum, 'slug' => 'serum-the-ordinary-hyaluronic-acid-2-b5', 'name' => 'Tinh Chất Cấp Nước The Ordinary Hyaluronic Acid 2% + B5', 'inci' => 'Sodium Hyaluronate', 'sku' => 'TO-HA-30ML', 'price' => 235000, 'size' => '30ml'],
            ['vendor' => $vendor3, 'brand' => $brandTO, 'cat' => $catSerum, 'slug' => 'serum-the-ordinary-alpha-arbutin-2-ha', 'name' => 'Tinh Chất Dưỡng Sáng The Ordinary Alpha Arbutin 2% + HA', 'inci' => 'Alpha-Arbutin', 'sku' => 'TO-ARB-30ML', 'price' => 290000, 'size' => '30ml'],
            ['vendor' => $vendor3, 'brand' => $brandKlairs, 'cat' => $catMoisturizer, 'slug' => 'kem-duong-klairs-midnight-blue-calming-cream', 'name' => 'Kem Dưỡng Làm Dịu Da Klairs Midnight Blue Calming Cream', 'inci' => 'Centella Asiatica Extract', 'sku' => 'KLA-BLUE-60ML', 'price' => 445000, 'size' => '60ml'],
            ['vendor' => $vendor3, 'brand' => $brandInnisfree, 'cat' => $catCleanser, 'slug' => 'sua-rua-mat-innisfree-volcanic-pore-bha', 'name' => 'Sữa Rửa Mặt Đá Núi Lửa Innisfree Super Volcanic Pore BHA', 'inci' => 'Salicylic Acid', 'sku' => 'INN-VOL-150ML', 'price' => 240000, 'size' => '150ml'],
            ['vendor' => $vendor3, 'brand' => $brandAnessa, 'cat' => $catSunscreen, 'slug' => 'gel-chong-nang-anessa-whitening-uv-gel', 'name' => 'Gel Chống Nắng Dưỡng Sáng Anessa Brightening UV Gel 90g', 'inci' => 'Tranexamic Acid', 'sku' => 'ANE-GEL-90G', 'price' => 495000, 'size' => '90g'],
            ['vendor' => $vendor3, 'brand' => $brandMAC, 'cat' => $catLipstick, 'slug' => 'son-kem-mac-powder-kiss-liquid-devoted-to-chili', 'name' => 'Son Kem Lì MAC Powder Kiss Liquid Lipcolour Devoted To Chili', 'inci' => 'Tocopherol', 'sku' => 'MAC-PK-DEVOTED', 'price' => 690000, 'size' => '5ml'],
            ['vendor' => $vendor3, 'brand' => $brandCeraVe, 'cat' => $catCleanser, 'slug' => 'sua-rua-mat-cerave-sa-smoothing-cleanser', 'name' => 'Sữa Rửa Mặt Giảm Mụn Sần CeraVe SA Smoothing Cleanser', 'inci' => 'Salicylic Acid', 'sku' => 'CRV-SA-236ML', 'price' => 330000, 'size' => '236ml'],
            ['vendor' => $vendor1, 'brand' => $brandInnisfree, 'cat' => $catSerum, 'slug' => 'tinh-chat-innisfree-retinol-cica-repair-ampoule', 'name' => 'Tinh Chất Tái Tạo Da Innisfree Retinol Cica Repair Ampoule', 'inci' => 'Retinol', 'sku' => 'INN-RET-30ML', 'price' => 680000, 'size' => '30ml'],
            ['vendor' => $vendor1, 'brand' => $brandInnisfree, 'cat' => $catToner, 'slug' => 'nuoc-can-bang-innisfree-green-tea-hyaluronic-skin', 'name' => 'Nước Cân Bằng Trà Xanh Innisfree Green Tea Hyaluronic Skin', 'inci' => 'Camellia Sinensis Leaf Extract', 'sku' => 'INN-GTS-170ML', 'price' => 390000, 'size' => '170ml'],
            ['vendor' => $vendor1, 'brand' => $brandLRP, 'cat' => $catSerum, 'slug' => 'tinh-chat-la-roche-posay-hyalu-b5-serum', 'name' => 'Tinh Chất Phục Hồi Căng Mịn La Roche-Posay Hyalu B5 Serum', 'inci' => 'Panthenol', 'sku' => 'LRP-HYALU-30ML', 'price' => 860000, 'size' => '30ml'],
            ['vendor' => $vendor1, 'brand' => $brandLRP, 'cat' => $catSerum, 'slug' => 'tinh-chat-la-roche-posay-effaclar-ultra-concentrated', 'name' => 'Tinh Chất Giảm Mụn La Roche-Posay Effaclar Ultra Concentrated Serum', 'inci' => 'Salicylic Acid', 'sku' => 'LRP-EFFSER-30ML', 'price' => 790000, 'size' => '30ml'],
            ['vendor' => $vendor1, 'brand' => $brandCeraVe, 'cat' => $catSerum, 'slug' => 'tinh-chat-cerave-resurfacing-retinol-serum', 'name' => 'Tinh Chất Mờ Thâm Mụn CeraVe Resurfacing Retinol Serum', 'inci' => 'Retinol', 'sku' => 'CRV-RET-30ML', 'price' => 460000, 'size' => '30ml'],
            ['vendor' => $vendor1, 'brand' => $brandAnessa, 'cat' => $catSunscreen, 'slug' => 'sua-chong-nang-anessa-mild-milk-da-nhay-cam', 'name' => 'Sữa Chống Nắng Dịu Nhẹ Anessa Moisture UV Sunscreen Mild Milk', 'inci' => 'Glycerin', 'sku' => 'ANE-MILD-60ML', 'price' => 565000, 'size' => '60ml'],
            ['vendor' => $vendor2, 'brand' => $brandKlairs, 'cat' => $catSerum, 'slug' => 'tinh-chat-klairs-freshly-juiced-vitamin-drop', 'name' => 'Tinh Chất Dưỡng Sáng Klairs Freshly Juiced Vitamin Drop 35ml', 'inci' => 'Ascorbic Acid', 'sku' => 'KLA-VITC-35ML', 'price' => 355000, 'size' => '35ml'],
            ['vendor' => $vendor2, 'brand' => $brandTO, 'cat' => $catToner, 'slug' => 'nuoc-can-bang-the-ordinary-glycolic-acid-7', 'name' => 'Nước Hoa Hồng Tẩy Tế Bào Chết The Ordinary Glycolic Acid 7%', 'inci' => 'Glycolic Acid', 'sku' => 'TO-GLY-240ML', 'price' => 320000, 'size' => '240ml'],
            ['vendor' => $vendor2, 'brand' => $brandTO, 'cat' => $catSerum, 'slug' => 'tinh-chat-the-ordinary-salicylic-acid-2', 'name' => 'Tinh Chất Giảm Mụn Đầu Đen The Ordinary Salicylic Acid 2% Solution', 'inci' => 'Salicylic Acid', 'sku' => 'TO-SAL-30ML', 'price' => 245000, 'size' => '30ml'],
            ['vendor' => $vendor2, 'brand' => $brandMAC, 'cat' => $catLipstick, 'slug' => 'son-thoi-mac-locked-kiss-ink-ruby-woo', 'name' => 'Son Thỏi Lì MAC Retro Matte Lipstick - Màu Ruby Woo Đỏ Thuần', 'inci' => 'Tocopherol', 'sku' => 'MAC-LIP-RUBYWOO', 'price' => 620000, 'size' => '3g'],
            ['vendor' => $vendor2, 'brand' => $brandKlairs, 'cat' => $catSunscreen, 'slug' => 'kem-chong-nang-klairs-all-day-airy-sunscreen', 'name' => 'Kem Chống Nắng Mỏng Nhẹ Klairs All-Day Airy Sunscreen SPF50+', 'inci' => 'Niacinamide', 'sku' => 'KLA-SUN-50G', 'price' => 345000, 'size' => '50g'],
        ];

        foreach ($extraCatalog as $idx => $item) {
            $prod = Product::query()->firstOrCreate(
                ['slug' => $item['slug']],
                [
                    'vendor_id' => $item['vendor']->id,
                    'brand_id' => $item['brand']?->id,
                    'category_id' => $item['cat']->id,
                    'name' => $item['name'],
                    'description' => 'Sản phẩm chính hãng phân phối tại hệ thống BeautyPals với thông tin thành phần chuẩn INCI và quản lý hạn dùng theo lô FEFO.',
                    'status' => 'approved',
                    'moderated_by' => $admin?->id,
                    'moderated_at' => now(),
                ],
            );

            $this->attachIngredients($prod, [
                ['inci' => $item['inci'], 'pos' => 1, 'conc' => 2.00],
                ['inci' => 'Glycerin', 'pos' => 2, 'conc' => 5.00],
            ]);
            $this->createImages($prod, [
                ['path' => 'products/demo_product_'.($idx + 10).'.jpg', 'sort' => 1],
            ]);
            $this->createSkus($prod, [
                ['code' => $item['sku'], 'name' => 'Tiêu chuẩn '.$item['size'], 'price' => $item['price'], 'size' => $item['size']],
            ]);
        }
    }

    private function attachSkinTypes(Product $product, array $skinTypeNames): void
    {
        $ids = SkinType::whereIn('name', $skinTypeNames)->pluck('id');
        $product->skinTypes()->syncWithoutDetaching($ids);
    }

    private function attachIngredients(Product $product, array $items): void
    {
        foreach ($items as $item) {
            $ing = Ingredient::where('inci_name', $item['inci'])->first();
            if ($ing) {
                $product->ingredients()->syncWithoutDetaching([
                    $ing->id => [
                        'position' => $item['pos'],
                        'concentration' => $item['conc'] ?? null,
                    ],
                ]);
            }
        }
    }

    private function createImages(Product $product, array $images): void
    {
        foreach ($images as $img) {
            ProductImage::query()->firstOrCreate(
                ['product_id' => $product->id, 'sort' => $img['sort']],
                ['path' => $img['path']],
            );
        }
    }

    private function createSkus(Product $product, array $skus): void
    {
        foreach ($skus as $sku) {
            Sku::query()->firstOrCreate(
                ['sku_code' => $sku['code']],
                [
                    'product_id' => $product->id,
                    'variant_name' => $sku['name'],
                    'price' => $sku['price'],
                    'size' => $sku['size'] ?? null,
                    'is_active' => true,
                ],
            );
        }
    }
}
