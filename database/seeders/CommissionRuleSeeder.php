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
     * - Category-specific rules only when the category slug exists.
     */
    public function run(): void
    {
        // Default (platform-wide) rule
        $exists = CommissionRule::whereNull('category_id')
            ->where('effective_from', '2026-10-01')
            ->exists();

        if (! $exists) {
            CommissionRule::create([
                'category_id'    => null,
                'rate'           => '10.00',
                'effective_from' => '2026-10-01',
                'is_active'      => true,
            ]);
            $this->command?->info('Created default commission rule: 10.00%');
        } else {
            $this->command?->info('Default commission rule already exists — skipped.');
        }

        // Sample category-specific rules (only if the category exists)
        $sampleRules = [
            'cham-soc-da' => '15.00',
            'trang-diem'  => '12.00',
        ];

        foreach ($sampleRules as $slug => $rate) {
            $category = Category::where('slug', $slug)->first();

            if ($category === null) {
                $this->command?->warn("Category slug '{$slug}' not found — skipping commission rule.");
                continue;
            }

            $exists = CommissionRule::where('category_id', $category->id)
                ->where('effective_from', '2026-10-01')
                ->exists();

            if (! $exists) {
                CommissionRule::create([
                    'category_id'    => $category->id,
                    'rate'           => $rate,
                    'effective_from' => '2026-10-01',
                    'is_active'      => true,
                ]);
                $this->command?->info("Created commission rule for '{$slug}': {$rate}%");
            }
        }
    }
}
