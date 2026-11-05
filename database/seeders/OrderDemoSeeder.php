<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Sku;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderDemoSeeder extends Seeder
{
    public function run(): void
    {
        $customer1 = User::where('email', 'customer@beautypals.test')->first();
        $customer2 = User::where('email', 'customer2@example.com')->first();
        $vendor1 = Vendor::where('store_name', 'BeautyPals Official Store')->first();
        $vendor2 = Vendor::where('store_name', 'An Minh Cosmetics Store')->first();
        $voucherFreeship = Voucher::where('code', 'FREESHIP')->first();

        // ---------------------------------------------------------------------
        // Order 1: Completed Order (Vendor 1)
        // ---------------------------------------------------------------------
        $sku1 = Sku::where('sku_code', 'INN-GTC-50ML')->first();
        $sku2 = Sku::where('sku_code', 'CRV-FOAM-236ML')->first();

        $order1 = Order::query()->firstOrCreate(
            ['order_code' => 'ORD-2026-1001'],
            [
                'customer_id' => $customer1->id,
                'receiver' => 'Customer Demo',
                'phone' => '0900000002',
                'address_text' => '123 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh',
                'subtotal' => 730000,
                'shipping_total' => 30000,
                'discount_total' => 30000,
                'grand_total' => 730000,
                'payment_method' => 'cod',
                'payment_status' => 'paid',
                'status' => 'completed',
                'voucher_id' => $voucherFreeship?->id,
                'idempotency_key' => (string) Str::uuid(),
                'placed_at' => now()->subDays(5),
            ],
        );

        if ($voucherFreeship) {
            VoucherRedemption::query()->firstOrCreate(
                ['voucher_id' => $voucherFreeship->id, 'order_id' => $order1->id],
                [
                    'user_id' => $customer1->id,
                    'amount' => 30000,
                    'created_at' => now()->subDays(5),
                ],
            );
        }

        Payment::query()->firstOrCreate(
            ['order_id' => $order1->id],
            [
                'method' => 'cod',
                'amount' => 730000,
                'status' => 'paid',
                'txn_ref' => 'COD-2026-1001',
                'paid_at' => now()->subDays(2),
            ],
        );

        $vo1 = VendorOrder::query()->firstOrCreate(
            ['order_id' => $order1->id, 'vendor_id' => $vendor1->id],
            [
                'vo_code' => 'VO-2026-1001-A',
                'status' => 'completed',
                'subtotal' => 730000,
                'shipping_fee' => 30000,
                'discount' => 30000,
                'total' => 730000,
                'commission_amount' => 73000,
                'tracking_code' => 'VNPOST-100101',
                'confirmed_at' => now()->subDays(5),
                'shipped_at' => now()->subDays(4),
                'delivered_at' => now()->subDays(2),
                'completed_at' => now()->subDays(1),
            ],
        );

        $item1 = OrderItem::query()->firstOrCreate(
            ['vendor_order_id' => $vo1->id, 'sku_id' => $sku1->id],
            [
                'product_name' => 'Kem Dưỡng Ẩm Trà Xanh Innisfree Green Tea Seed Hyaluronic Cream',
                'sku_code' => $sku1->sku_code,
                'unit_price' => 450000,
                'qty' => 1,
                'line_total' => 450000,
                'commission_rate' => '10.00',
                'commission_amount' => 45000,
            ],
        );

        $item2 = OrderItem::query()->firstOrCreate(
            ['vendor_order_id' => $vo1->id, 'sku_id' => $sku2->id],
            [
                'product_name' => 'Sữa Rửa Mặt CeraVe Foaming Facial Cleanser Cho Da Dầu Mụn',
                'sku_code' => $sku2->sku_code,
                'unit_price' => 280000,
                'qty' => 1,
                'line_total' => 280000,
                'commission_rate' => '10.00',
                'commission_amount' => 28000,
            ],
        );

        // Allocations
        $batch1 = Batch::where('sku_id', $sku1->id)->first();
        if ($batch1) {
            OrderItemAllocation::query()->firstOrCreate(
                ['order_item_id' => $item1->id, 'batch_id' => $batch1->id],
                ['qty' => 1, 'status' => 'fulfilled'],
            );
        }

        $batch2 = Batch::where('sku_id', $sku2->id)->first();
        if ($batch2) {
            OrderItemAllocation::query()->firstOrCreate(
                ['order_item_id' => $item2->id, 'batch_id' => $batch2->id],
                ['qty' => 1, 'status' => 'fulfilled'],
            );
        }

        // Status history
        $this->createStatusHistory($vo1->id, [
            ['from' => null, 'to' => 'pending', 'days' => 5, 'note' => 'Đơn hàng được tạo thành công'],
            ['from' => 'pending', 'to' => 'confirmed', 'days' => 5, 'note' => 'Người bán đã xác nhận đơn'],
            ['from' => 'confirmed', 'to' => 'shipped', 'days' => 4, 'note' => 'Đã giao cho đơn vị vận chuyển VNPost'],
            ['from' => 'shipped', 'to' => 'delivered', 'days' => 2, 'note' => 'Giao hàng thành công tới khách'],
            ['from' => 'delivered', 'to' => 'completed', 'days' => 1, 'note' => 'Đơn hàng hoàn tất'],
        ]);

        // ---------------------------------------------------------------------
        // Order 2: Multi-Vendor In-Progress Order (Vendor 1 + Vendor 2)
        // ---------------------------------------------------------------------
        $sku3 = Sku::where('sku_code', 'LRP-ANT-50ML')->first();
        $sku4 = Sku::where('sku_code', 'TO-NIA-30ML')->first();

        $order2 = Order::query()->firstOrCreate(
            ['order_code' => 'ORD-2026-1002'],
            [
                'customer_id' => $customer1->id,
                'receiver' => 'Customer Demo',
                'phone' => '0900000002',
                'address_text' => '123 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh',
                'subtotal' => 680000,
                'shipping_total' => 45000,
                'discount_total' => 0,
                'grand_total' => 725000,
                'payment_method' => 'vnpay',
                'payment_status' => 'paid',
                'status' => 'processing',
                'idempotency_key' => (string) Str::uuid(),
                'placed_at' => now()->subDay(),
            ],
        );

        Payment::query()->firstOrCreate(
            ['order_id' => $order2->id],
            [
                'method' => 'vnpay',
                'amount' => 725000,
                'status' => 'paid',
                'txn_ref' => 'VNPAY-2026-1002',
                'paid_at' => now()->subDay(),
            ],
        );

        // VendorOrder 1 (Store 1: Shipped)
        $vo2A = VendorOrder::query()->firstOrCreate(
            ['order_id' => $order2->id, 'vendor_id' => $vendor1->id],
            [
                'vo_code' => 'VO-2026-1002-A',
                'status' => 'shipped',
                'subtotal' => 485000,
                'shipping_fee' => 25000,
                'discount' => 0,
                'total' => 510000,
                'commission_amount' => 48500,
                'tracking_code' => 'GHN-882910',
                'confirmed_at' => now()->subHours(20),
                'shipped_at' => now()->subHours(10),
            ],
        );

        OrderItem::query()->firstOrCreate(
            ['vendor_order_id' => $vo2A->id, 'sku_id' => $sku3->id],
            [
                'product_name' => 'Kem Chống Nắng La Roche-Posay Anthelios UVMune 400 Oil Control Gel-Cream 50ml',
                'sku_code' => $sku3->sku_code,
                'unit_price' => 485000,
                'qty' => 1,
                'line_total' => 485000,
                'commission_rate' => '10.00',
                'commission_amount' => 48500,
            ],
        );

        // VendorOrder 2 (Store 2: Confirmed)
        $vo2B = VendorOrder::query()->firstOrCreate(
            ['order_id' => $order2->id, 'vendor_id' => $vendor2->id],
            [
                'vo_code' => 'VO-2026-1002-B',
                'status' => 'confirmed',
                'subtotal' => 195000,
                'shipping_fee' => 20000,
                'discount' => 0,
                'total' => 215000,
                'commission_amount' => 19500,
                'confirmed_at' => now()->subHours(18),
            ],
        );

        OrderItem::query()->firstOrCreate(
            ['vendor_order_id' => $vo2B->id, 'sku_id' => $sku4->id],
            [
                'product_name' => 'Tinh Chất The Ordinary Niacinamide 10% + Zinc 1% Giảm Thâm Mụn',
                'sku_code' => $sku4->sku_code,
                'unit_price' => 195000,
                'qty' => 1,
                'line_total' => 195000,
                'commission_rate' => '10.00',
                'commission_amount' => 19500,
            ],
        );

        // ---------------------------------------------------------------------
        // Order 3: Pending Order (Customer 2)
        // ---------------------------------------------------------------------
        $sku5 = Sku::where('sku_code', 'MAC-LIP-CHILI')->first();

        $order3 = Order::query()->firstOrCreate(
            ['order_code' => 'ORD-2026-1003'],
            [
                'customer_id' => $customer2->id,
                'receiver' => 'Nguyễn Văn An',
                'phone' => '0900000006',
                'address_text' => '99 Trần Hưng Đạo, Quận 5, TP. Hồ Chí Minh',
                'subtotal' => 610000,
                'shipping_total' => 25000,
                'discount_total' => 0,
                'grand_total' => 635000,
                'payment_method' => 'cod',
                'payment_status' => 'pending',
                'status' => 'pending',
                'idempotency_key' => (string) Str::uuid(),
                'placed_at' => now()->subHours(2),
            ],
        );

        $vo3 = VendorOrder::query()->firstOrCreate(
            ['order_id' => $order3->id, 'vendor_id' => $vendor2->id],
            [
                'vo_code' => 'VO-2026-1003-A',
                'status' => 'pending',
                'subtotal' => 610000,
                'shipping_fee' => 25000,
                'discount' => 0,
                'total' => 635000,
                'commission_amount' => 61000,
            ],
        );

        OrderItem::query()->firstOrCreate(
            ['vendor_order_id' => $vo3->id, 'sku_id' => $sku5->id],
            [
                'product_name' => 'Son Thỏi MAC Matte Lipstick - Màu Chili Đỏ Gạch',
                'sku_code' => $sku5->sku_code,
                'unit_price' => 610000,
                'qty' => 1,
                'line_total' => 610000,
                'commission_rate' => '10.00',
                'commission_amount' => 61000,
            ],
        );
    }

    private function createStatusHistory(int $voId, array $history): void
    {
        foreach ($history as $h) {
            OrderStatusHistory::query()->firstOrCreate(
                [
                    'vendor_order_id' => $voId,
                    'to_status' => $h['to'],
                ],
                [
                    'from_status' => $h['from'],
                    'note' => $h['note'],
                    'created_at' => now()->subDays($h['days']),
                ],
            );
        }
    }
}
