<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sku;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SprintThreeDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Dữ liệu Sprint 3 chỉ dành cho môi trường local/testing.');
        }

        DB::transaction(function () {
            $owner = User::firstOrCreate(['email' => 'vendor.s3@beautypals.test'], [
                'name' => 'Vendor Sprint 3', 'password' => DemoUserSeeder::PASSWORD, 'role' => 'vendor', 'status' => 'active',
            ]);
            $vendor = Vendor::firstOrCreate(['owner_user_id' => $owner->id], [
                'store_name' => 'BeautyPals Sprint 3 Demo', 'phone' => '0900000304', 'address' => 'TP Hồ Chí Minh',
                'tax_code' => 'S3-DEMO-03040506', 'license_file' => 'demo/licenses/sprint-three.pdf',
                'status' => 'approved', 'approved_at' => now(),
            ]);
            $customer = User::firstOrCreate(['email' => 'customer.s3@beautypals.test'], [
                'name' => 'Khách thử Sprint 3', 'password' => DemoUserSeeder::PASSWORD, 'role' => 'customer', 'status' => 'active',
            ]);
            $category = Category::firstOrCreate(['slug' => 'sprint-3-demo'], ['name' => 'Sản phẩm demo Sprint 3']);
            $product = Product::firstOrCreate(['slug' => 'serum-sprint-3-demo'], [
                'vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Serum dưỡng da Sprint 3', 'status' => 'approved',
            ]);
            $sku = Sku::firstOrCreate(['sku_code' => 'S3-SERUM-30ML'], [
                'product_id' => $product->id, 'variant_name' => '30 ml', 'price' => 100000, 'is_active' => true,
            ]);
            $batches = [];
            foreach (['S3-LOT-NEAR' => 30, 'S3-LOT-FAR' => 90, 'S3-LOT-TODAY' => 0, 'S3-LOT-PAST' => -1] as $batchNo => $days) {
                $batches[] = Batch::firstOrCreate(['sku_id' => $sku->id, 'batch_no' => $batchNo], [
                    'mfg_date' => today('Asia/Ho_Chi_Minh')->subMonths(3),
                    'exp_date' => today('Asia/Ho_Chi_Minh')->addDays($days),
                    'qty_received' => 100, 'qty_on_hand' => 100, 'qty_reserved' => 0, 'status' => 'active',
                ]);
            }

            for ($i = 1; $i <= 20; $i++) {
                $number = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                $status = match ($i) {
                    17 => 'confirmed', 18 => 'packed', 19 => 'rejected', 20 => 'completed', default => 'pending'
                };
                $order = Order::firstOrCreate(['order_code' => 'S3-ORD-'.$number], [
                    'customer_id' => $customer->id, 'receiver' => 'Khách thử Sprint 3', 'phone' => '0900000304',
                    'address_text' => '123 Nguyễn Huệ, TP Hồ Chí Minh (dữ liệu demo)', 'subtotal' => 300000,
                    'shipping_total' => 20000, 'grand_total' => 320000, 'payment_method' => 'cod',
                    'status' => match ($status) {
                        'rejected' => 'cancelled', 'completed' => 'completed', 'pending' => 'pending', default => 'processing'
                    },
                    'idempotency_key' => (string) Str::uuid(), 'placed_at' => now()->subMinutes(21 - $i),
                ]);
                $vo = VendorOrder::firstOrCreate(['vo_code' => 'S3-VO-'.$number], [
                    'order_id' => $order->id, 'vendor_id' => $vendor->id, 'status' => $status, 'subtotal' => 300000,
                    'shipping_fee' => 20000, 'total' => 320000, 'commission_amount' => 30000,
                    'confirmed_at' => in_array($status, ['confirmed', 'packed', 'completed']) ? now() : null,
                    'shipped_at' => $status === 'completed' ? now() : null,
                    'delivered_at' => $status === 'completed' ? now() : null,
                    'completed_at' => $status === 'completed' ? now() : null,
                ]);
                // Never reset an order or its stock when the demo seeder is run again.
                if (! $vo->wasRecentlyCreated) {
                    continue;
                }
                $item = $vo->items()->create([
                    'sku_id' => $sku->id, 'product_name' => $product->name, 'sku_code' => $sku->sku_code,
                    'unit_price' => 100000, 'qty' => 3, 'line_total' => 300000, 'commission_rate' => 10, 'commission_amount' => 30000,
                ]);
                foreach ([$batches[0]->id => 2, $batches[1]->id => 1] as $batchId => $qty) {
                    $allocationStatus = match ($status) {
                        'rejected' => 'released', 'completed' => 'committed', default => 'held'
                    };
                    $item->allocations()->create(['batch_id' => $batchId, 'qty' => $qty, 'status' => $allocationStatus]);
                    $batch = Batch::findOrFail($batchId);
                    if ($allocationStatus === 'held') {
                        $batch->increment('qty_reserved', $qty);
                    } elseif ($allocationStatus === 'committed') {
                        $batch->decrement('qty_on_hand', $qty);
                    }
                    $batch->stockMovements()->create([
                        'type' => match ($allocationStatus) {
                            'held' => 'reserve', 'committed' => 'commit', default => 'release'
                        },
                        'qty' => $allocationStatus === 'released' ? $qty : -$qty,
                        'ref_type' => 'vendor_order', 'ref_id' => $vo->id, 'created_by' => $owner->id,
                    ]);
                }
                $vo->statusHistories()->create([
                    'from_status' => null, 'to_status' => 'pending', 'note' => 'Đơn demo Sprint 3', 'created_at' => $order->placed_at,
                ]);
                $path = match ($status) {
                    'confirmed' => ['confirmed'], 'packed' => ['confirmed', 'packed'],
                    'rejected' => ['rejected'], 'completed' => ['confirmed', 'packed', 'shipped', 'delivered', 'completed'],
                    default => [],
                };
                $from = 'pending';
                foreach ($path as $to) {
                    $vo->statusHistories()->create([
                        'from_status' => $from, 'to_status' => $to, 'changed_by' => $owner->id,
                        'note' => $to === 'rejected' ? 'Đơn demo bị từ chối do sản phẩm hư hỏng' : 'Trạng thái mẫu Sprint 3',
                    ]);
                    $from = $to;
                }
            }
        });

        $this->command?->info('Demo Sprint 3: vendor.s3@beautypals.test / '.DemoUserSeeder::PASSWORD.' · 20 đơn, 4 lô. Không đặt lại dữ liệu đã thao tác.');
    }
}
