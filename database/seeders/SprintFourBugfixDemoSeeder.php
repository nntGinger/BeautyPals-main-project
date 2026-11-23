<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Batch;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sku;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SprintFourBugfixDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo S4-05 chỉ dành cho local/testing.');
        }

        DB::transaction(function () {
            $customer = $this->user('customer.s4@beautypals.test', 'Khách test S4-05', 'customer');
            $this->user('admin.s4@beautypals.test', 'Admin test S4-05', 'admin');
            Address::firstOrCreate(['user_id' => $customer->id, 'line' => '123 Demo Sprint 4'], [
                'receiver' => $customer->name, 'phone' => '0900000405', 'ward' => 'Phường demo',
                'district' => 'Quận demo', 'city' => 'TP Hồ Chí Minh', 'is_default' => true,
            ]);
            $category = Category::firstOrCreate(['slug' => 's4-demo'], ['name' => 'Demo kiểm thử Sprint 4']);
            $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
            foreach (['past' => [-1, 'HSD hôm qua'], 'today' => [0, 'HSD hôm nay'], 'future' => [30, 'Còn hạn'], 'locked' => [30, 'Vendor bị khóa']] as $key => [$days, $label]) {
                $locked = $key === 'locked';
                $owner = $this->user($locked ? 'vendor.locked.s4@beautypals.test' : 'vendor.s4@beautypals.test', $locked ? 'Vendor bị khóa S4' : 'Vendor S4', 'vendor', $locked ? 'locked' : 'active');
                $vendor = Vendor::firstOrCreate(['owner_user_id' => $owner->id], [
                    'store_name' => $locked ? 'Cửa hàng bị khóa S4' : 'Cửa hàng demo S4',
                    'tax_code' => $locked ? 'S4-LOCKED-405' : 'S4-ACTIVE-405', 'license_file' => 'demo/s4.pdf',
                    'status' => 'approved', 'approved_at' => now(),
                ]);
                $product = Product::firstOrCreate(['slug' => 's4-demo-'.$key], [
                    'vendor_id' => $vendor->id, 'category_id' => $category->id,
                    'name' => 'S4 DEMO - '.$label, 'status' => 'approved',
                ]);
                $sku = Sku::firstOrCreate(['sku_code' => 'S4-DEMO-'.strtoupper($key)], [
                    'product_id' => $product->id, 'variant_name' => 'Demo', 'price' => 100000, 'is_active' => true,
                ]);
                $batch = Batch::firstOrCreate(['sku_id' => $sku->id, 'batch_no' => 'S4-LOT-'.strtoupper($key)], [
                    'mfg_date' => Carbon::today('Asia/Ho_Chi_Minh')->subMonths(3),
                    'exp_date' => Carbon::today('Asia/Ho_Chi_Minh')->addDays($days),
                    'qty_received' => 10, 'qty_on_hand' => 10, 'qty_reserved' => 0, 'status' => 'active',
                ]);
                if ($batch->wasRecentlyCreated) {
                    // Stale cart fixtures deliberately bypass addItem validation.
                    // They simulate goods added before expiry or account locking.
                    $cart->items()->firstOrCreate(['sku_id' => $sku->id], ['qty' => 1]);
                }
            }
        });
        $this->command?->info('Demo S4-05 sẵn sàng. customer.s4@beautypals.test / Password123!');
    }

    private function user(string $email, string $name, string $role, string $status = 'active'): User
    {
        return User::firstOrCreate(['email' => $email], [
            'name' => $name, 'password' => DemoUserSeeder::PASSWORD, 'role' => $role, 'status' => $status,
        ]);
    }
}
