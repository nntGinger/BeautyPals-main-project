<?php

namespace App\Services\Inventory;

use App\Exceptions\InventoryException;
use App\Models\Batch;
use App\Models\OrderItemAllocation;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    /**
     * Reserve inventory for an order item using FEFO (First Expired, First Out).
     *
     * Must be executed inside a transaction; if called standalone, wraps in a transaction.
     *
     * @return Collection<int, OrderItemAllocation>
     */
    public function reserve(
        int $orderItemId,
        int $skuId,
        int $requestedQty,
        ?int $actorId = null,
    ): Collection {
        if ($requestedQty <= 0) {
            throw InventoryException::invalidQuantity();
        }

        return DB::transaction(function () use (
            $orderItemId,
            $skuId,
            $requestedQty,
            $actorId,
        ): Collection {
            $today = now()->toDateString();

            $batches = Batch::query()
                ->where('sku_id', $skuId)
                ->where('status', 'active')
                ->whereDate('exp_date', '>=', $today)
                ->whereColumn('qty_on_hand', '>', 'qty_reserved')
                ->orderBy('exp_date', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            $remaining = $requestedQty;
            $allocations = collect();

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $available = (int) $batch->qty_on_hand - (int) $batch->qty_reserved;
                if ($available <= 0) {
                    continue;
                }

                $take = min($remaining, $available);

                $batch->update([
                    'qty_reserved' => (int) $batch->qty_reserved + $take,
                ]);

                $allocation = OrderItemAllocation::query()->create([
                    'order_item_id' => $orderItemId,
                    'batch_id' => $batch->id,
                    'qty' => $take,
                    'status' => 'held',
                    'expires_at' => null,
                ]);

                StockMovement::query()->create([
                    'batch_id' => $batch->id,
                    'type' => 'reserve',
                    'qty' => -$take,
                    'ref_type' => 'order_item',
                    'ref_id' => $orderItemId,
                    'created_by' => $actorId,
                    'created_at' => now(),
                ]);

                $allocations->push($allocation);
                $remaining -= $take;
            }

            if ($remaining > 0) {
                $totalAvailable = $requestedQty - $remaining;
                throw InventoryException::insufficientStock($skuId, $requestedQty, $totalAvailable);
            }

            return $allocations;
        });
    }
}
