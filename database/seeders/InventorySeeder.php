<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Sku;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();

        // 1. Seed batches for all SKUs
        $skus = Sku::all();

        foreach ($skus as $sku) {
            if ($sku->sku_code === 'INN-GTC-50ML') {
                // Batch 1 (Nearer expiry - 6 months ahead, FEFO test target)
                $b1 = Batch::query()->firstOrCreate(
                    ['sku_id' => $sku->id, 'batch_no' => 'LOT-2026-001'],
                    [
                        'mfg_date' => now()->subMonths(6)->toDateString(),
                        'exp_date' => now()->addMonths(6)->toDateString(),
                        'qty_received' => 50,
                        'qty_on_hand' => 45,
                        'qty_reserved' => 5,
                        'status' => 'active',
                    ],
                );
                $this->createStockMovement($b1, 50, $admin?->id);

                // Batch 2 (Further expiry - 24 months ahead)
                $b2 = Batch::query()->firstOrCreate(
                    ['sku_id' => $sku->id, 'batch_no' => 'LOT-2026-002'],
                    [
                        'mfg_date' => now()->subMonths(1)->toDateString(),
                        'exp_date' => now()->addMonths(24)->toDateString(),
                        'qty_received' => 100,
                        'qty_on_hand' => 100,
                        'qty_reserved' => 0,
                        'status' => 'active',
                    ],
                );
                $this->createStockMovement($b2, 100, $admin?->id);

                continue;
            }

            // Standard batch for other SKUs
            $batchNo = 'LOT-'.strtoupper(substr(md5($sku->sku_code), 0, 6));
            $batch = Batch::query()->firstOrCreate(
                ['sku_id' => $sku->id, 'batch_no' => $batchNo],
                [
                    'mfg_date' => now()->subMonths(2)->toDateString(),
                    'exp_date' => now()->addMonths(22)->toDateString(),
                    'qty_received' => 100,
                    'qty_on_hand' => 90,
                    'qty_reserved' => 5,
                    'status' => 'active',
                ],
            );

            $this->createStockMovement($batch, 100, $admin?->id);
        }
    }

    private function createStockMovement(Batch $batch, int $qty, ?int $userId): void
    {
        StockMovement::query()->firstOrCreate(
            [
                'batch_id' => $batch->id,
                'type' => 'inbound_purchase',
            ],
            [
                'qty' => $qty,
                'ref_type' => 'purchase_order',
                'ref_id' => 1,
                'created_by' => $userId,
                'created_at' => now(),
            ],
        );
    }
}
