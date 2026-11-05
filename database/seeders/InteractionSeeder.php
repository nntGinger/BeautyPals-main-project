<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Notification;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Sku;
use App\Models\User;
use Illuminate\Database\Seeder;

class InteractionSeeder extends Seeder
{
    public function run(): void
    {
        $customer1 = User::where('email', 'customer@beautypals.test')->first();

        // 1. Reviews for completed order items
        $orderItem1 = OrderItem::where('sku_code', 'INN-GTC-50ML')->first();
        if ($orderItem1) {
            Review::query()->firstOrCreate(
                ['order_item_id' => $orderItem1->id],
                [
                    'product_id' => $orderItem1->sku->product_id,
                    'customer_id' => $customer1->id,
                    'rating' => 5,
                    'comment' => 'Kem dưỡng ẩm siêu thích, chất gel cream mỏng nhẹ thấm nhanh không hề bết rít. Dùng buổi tối sáng dậy thấy da căng mướt rõ rệt. Rất đáng tiền!',
                    'status' => 'visible',
                ],
            );
        }

        $orderItem2 = OrderItem::where('sku_code', 'CRV-FOAM-236ML')->first();
        if ($orderItem2) {
            Review::query()->firstOrCreate(
                ['order_item_id' => $orderItem2->id],
                [
                    'product_id' => $orderItem2->sku->product_id,
                    'customer_id' => $customer1->id,
                    'rating' => 5,
                    'comment' => 'Sữa rửa mặt tạo bọt mịn, rửa xong da sạch thoáng nhưng vẫn giữ được độ ẩm tự nhiên, không bị khô rát khó chịu. Dùng cực kỳ hợp cho da dầu mụn.',
                    'status' => 'visible',
                ],
            );
        }

        // 2. Shopping Cart for Customer Demo
        $cart = Cart::query()->firstOrCreate(['user_id' => $customer1->id]);
        $tonerSku = Sku::where('sku_code', 'KLA-TON-180ML')->first();
        if ($tonerSku) {
            CartItem::query()->firstOrCreate(
                ['cart_id' => $cart->id, 'sku_id' => $tonerSku->id],
                ['qty' => 1],
            );
        }

        // 3. Wishlist for Customer Demo
        $lrpProduct = Product::where('slug', 'kem-chong-nang-la-roche-posay-anthelios-oil-control')->first();
        $macProduct = Product::where('slug', 'son-thoi-mac-matte-lipstick-chili')->first();

        if ($lrpProduct) {
            $customer1->wishlistProducts()->syncWithoutDetaching([$lrpProduct->id]);
        }
        if ($macProduct) {
            $customer1->wishlistProducts()->syncWithoutDetaching([$macProduct->id]);
        }

        // 4. Notifications for Customer Demo
        Notification::query()->firstOrCreate(
            [
                'user_id' => $customer1->id,
                'type' => 'order_delivered',
            ],
            [
                'data' => [
                    'title' => 'Đơn hàng ORD-2026-1001 đã giao thành công',
                    'message' => 'Đơn hàng của bạn đã được giao đến 123 Nguyễn Huệ. Hãy đánh giá sản phẩm để nhận ưu đãi nhé!',
                    'order_code' => 'ORD-2026-1001',
                ],
                'read_at' => now()->subDay(),
                'created_at' => now()->subDays(2),
            ],
        );

        Notification::query()->firstOrCreate(
            [
                'user_id' => $customer1->id,
                'type' => 'welcome',
            ],
            [
                'data' => [
                    'title' => 'Chào mừng bạn đến với BeautyPals!',
                    'message' => 'Khám phá hàng trăm sản phẩm skincare và mỹ phẩm chính hãng cùng ưu đãi voucher chào mừng WELCOME50.',
                ],
                'read_at' => null,
                'created_at' => now()->subDays(5),
            ],
        );
    }
}
