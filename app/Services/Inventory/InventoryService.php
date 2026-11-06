<?php

namespace App\Services\Inventory;

use App\Exceptions\InventoryException;
use App\Models\Batch;
use App\Models\OrderItemAllocation;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Commit held allocations when a vendor order is shipped.
     *
     * Decrements both qty_on_hand and qty_reserved on each batch.
     * Idempotent: calling twice does not double-deduct.
     */
    public function commitForVendorOrder(int $vendorOrderId, ?int $actorId = null): void
    {
        DB::transaction(function () use ($vendorOrderId, $actorId): void {
            $allocations = OrderItemAllocation::query()
                ->whereHas('orderItem', fn ($q) => $q->where('vendor_order_id', $vendorOrderId))
                ->orderBy('batch_id', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($allocations as $allocation) {
                if ($allocation->status !== 'held') {
                    continue;
                }

                $batch = Batch::query()
                    ->whereKey($allocation->batch_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $qty = (int) $allocation->qty;

                if ((int) $batch->qty_on_hand < $qty || (int) $batch->qty_reserved < $qty) {
                    throw InventoryException::negativeStockNotAllowed($batch->id);
                }

                $batch->update([
                    'qty_on_hand' => (int) $batch->qty_on_hand - $qty,
                    'qty_reserved' => (int) $batch->qty_reserved - $qty,
                ]);

                $allocation->update([
                    'status' => 'committed',
                ]);

                StockMovement::query()->create([
                    'batch_id' => $batch->id,
                    'type' => 'commit',
                    'qty' => -$qty,
                    'ref_type' => 'vendor_order',
                    'ref_id' => $vendorOrderId,
                    'created_by' => $actorId,
                    'created_at' => now(),
                ]);
            }
        });
    }

    /**
     * Release held allocations when a vendor order is cancelled or rejected.
     *
     * Decrements qty_reserved on each batch and marks allocation as released.
     * Idempotent: calling twice does not double-release.
     */
    public function releaseForVendorOrder(int $vendorOrderId, ?int $actorId = null): void
    {
        DB::transaction(function () use ($vendorOrderId, $actorId): void {
            $allocations = OrderItemAllocation::query()
                ->whereHas('orderItem', fn ($q) => $q->where('vendor_order_id', $vendorOrderId))
                ->orderBy('batch_id', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($allocations as $allocation) {
                if ($allocation->status !== 'held') {
                    continue;
                }

                $batch = Batch::query()
                    ->whereKey($allocation->batch_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $qty = (int) $allocation->qty;

                if ((int) $batch->qty_reserved < $qty) {
                    throw InventoryException::negativeStockNotAllowed($batch->id);
                }

                $batch->update([
                    'qty_reserved' => (int) $batch->qty_reserved - $qty,
                ]);

                $allocation->update([
                    'status' => 'released',
                ]);

                StockMovement::query()->create([
                    'batch_id' => $batch->id,
                    'type' => 'release',
                    'qty' => $qty,
                    'ref_type' => 'vendor_order',
                    'ref_id' => $vendorOrderId,
                    'created_by' => $actorId,
                    'created_at' => now(),
                ]);
            }
        });
    }
}
