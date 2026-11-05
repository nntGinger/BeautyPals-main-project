<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CommissionRule;
use Illuminate\Database\Seeder;

class CommissionRuleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Platform-wide default rule (10%)
        CommissionRule::query()->firstOrCreate(
            [
                'category_id' => null,
                'effective_from' => '2026-10-01',
            ],
            [
                'rate' => '10.00',
                'is_active' => true,
            ],
        );

        // 2. Category-specific commission rules
        $categoryRules = [
            'cham-soc-da-mat' => '12.00',
            'trang-diem' => '15.00',
            'cham-soc-co-the' => '10.00',
        ];

        foreach ($categoryRules as $slug => $rate) {
            $cat = Category::where('slug', $slug)->first();
            if ($cat) {
                CommissionRule::query()->firstOrCreate(
                    [
                        'category_id' => $cat->id,
                        'effective_from' => '2026-10-01',
                    ],
                    [
                        'rate' => $rate,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
