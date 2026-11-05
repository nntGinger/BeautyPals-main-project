<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DemoUserSeeder::class,
            CatalogSeeder::class,
            ProductSeeder::class,
            InventorySeeder::class,
            PromotionSeeder::class,
            CommissionRuleSeeder::class,
            OrderDemoSeeder::class,
            InteractionSeeder::class,
        ]);
    }
}
