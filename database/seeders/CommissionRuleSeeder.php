<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CommissionRule;
use Illuminate\Database\Seeder;

class CommissionRuleSeeder extends Seeder
{
    /**
     * Seed commission rules.
     *
     * - 1 default rule (category_id NULL): 10.00%, effective 2026-10-01.
     * - Category-specific rules for catalog categories when they exist.
     */
    public function run(): void
    {
        // 1. Default (platform-wide) rule
        $defaultRule = CommissionRule::query()->firstOrCreate(
            [
                'category_id'    => null,
                'category_key'   => 0,
                'effective_from' => '2026-10-01',
            ],
            [
                'rate'      => '10.00',
                'is_active' => true,
            ],
        );

        if ($defaultRule->wasRecentlyCreated) {
            $this->command?->info('Created default commission rule: 10.00%');
        } else {
            $this->command?->info('Default commission rule already exists.');
        }

        // 2. Category-specific rules
        $sampleRules = [
            'cham-soc-da-mat' => '12.00',
            'cham-soc-da'     => '15.00',
            'trang-diem'      => '15.00',
            'cham-soc-co-the' => '10.00',
        ];

        foreach ($sampleRules as $slug => $rate) {
            $category = Category::where('slug', $slug)->first();

            if ($category === null) {
                continue;
            }

            $rule = CommissionRule::query()->firstOrCreate(
                [
                    'category_id'    => $category->id,
                    'category_key'   => $category->id,
                    'effective_from' => '2026-10-01',
                ],
                [
                    'rate'      => $rate,
                    'is_active' => true,
                ],
            );

            if ($rule->wasRecentlyCreated) {
                $this->command?->info("Created commission rule for '{$slug}': {$rate}%");
            }
        }
    }
}
