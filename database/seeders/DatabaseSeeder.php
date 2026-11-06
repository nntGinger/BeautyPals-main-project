<?php

namespace Database\Seeders;

use App\Observers\AuditableObserver;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        AuditableObserver::withoutAuditing(function (): void {
            $this->call([
                DemoUserSeeder::class,
                CatalogSeeder::class,
                IngredientSeeder::class,
                ProductSeeder::class,
                InventorySeeder::class,
                PromotionSeeder::class,
                CommissionRuleSeeder::class,
                OrderDemoSeeder::class,
                InteractionSeeder::class,
            ]);
        });
    }
}
