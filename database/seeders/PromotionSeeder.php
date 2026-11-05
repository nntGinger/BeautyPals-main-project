<?php

namespace Database\Seeders;

use App\Models\Voucher;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $vouchers = [
            [
                'code' => 'WELCOME50',
                'type' => 'fixed',
                'value' => 50000,
                'max_discount' => null,
                'min_order' => 200000,
                'starts_at' => now()->subMonth(),
                'ends_at' => now()->addMonths(6),
                'usage_limit' => 1000,
                'used_count' => 15,
                'status' => 'active',
            ],
            [
                'code' => 'BEAUTY10',
                'type' => 'percent',
                'value' => 10,
                'max_discount' => 100000,
                'min_order' => 300000,
                'starts_at' => now()->subMonth(),
                'ends_at' => now()->addMonths(6),
                'usage_limit' => 500,
                'used_count' => 28,
                'status' => 'active',
            ],
            [
                'code' => 'FREESHIP',
                'type' => 'fixed',
                'value' => 30000,
                'max_discount' => 30000,
                'min_order' => 150000,
                'starts_at' => now()->subMonth(),
                'ends_at' => now()->addMonths(6),
                'usage_limit' => 2000,
                'used_count' => 45,
                'status' => 'active',
            ],
            [
                'code' => 'EXPIRED20',
                'type' => 'fixed',
                'value' => 20000,
                'max_discount' => null,
                'min_order' => 100000,
                'starts_at' => now()->subMonths(3),
                'ends_at' => now()->subMonth(),
                'usage_limit' => 100,
                'used_count' => 100,
                'status' => 'expired',
            ],
        ];

        foreach ($vouchers as $item) {
            Voucher::query()->firstOrCreate(
                ['code' => $item['code']],
                $item,
            );
        }
    }
}
