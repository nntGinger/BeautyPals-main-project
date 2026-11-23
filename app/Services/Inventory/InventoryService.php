<?php

namespace App\Services\Inventory;

use App\Models\Batch;
use App\Models\OrderItemAllocation;
use App\Models\StockMovement;
use App\Models\VendorOrder;
use App\Support\InventoryDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * S3-08: commit held stock on shipment or release it when the order is cancelled.
 * Only held allocations are actionable, so repeating an operation is idempotent.
 */
class InventoryService
{
    /**
     * Convert held allocations to committed stock for a single vendor order.
     * Must be called in the same DB transaction as status -> shipped.
     *
     * @throws InventoryException
     */
    public function commitForVendorOrder(VendorOrder $vendorOrder, ?int $actorUserId = null): void
    {
        DB::transaction(function () use ($vendorOrder, $actorUserId): void {
            [$allocations, $batches] = $this->prepareLockedAllocations($vendorOrder, true);

            // All batches were locked and validated before any stock is changed.
            foreach ($allocations as $allocation) {
                $batch = $batches[(int) $allocation->batch_id];
                $quantity = (int) $allocation->qty;

                $batch->qty_on_hand -= $quantity;
                $batch->qty_reserved -= $quantity;
                $batch->save();

                $allocation->status = 'committed';
                $allocation->save();

                $this->logMovement($vendorOrder->id, $allocation, 'commit', -$quantity, $actorUserId);
            }
        }, 3);
    }

    /**
     * Release held allocations when a vendor order is cancelled or rejected.
     * Physical on-hand stock does not change on release.
     *
     * @throws InventoryException
     */
    public function releaseForVendorOrder(VendorOrder $vendorOrder, ?int $actorUserId = null): void
    {
        DB::transaction(function () use ($vendorOrder, $actorUserId): void {
            [$allocations, $batches] = $this->prepareLockedAllocations($vendorOrder, false);

            foreach ($allocations as $allocation) {
                $batch = $batches[(int) $allocation->batch_id];
                $quantity = (int) $allocation->qty;

                $batch->qty_reserved -= $quantity;
                $batch->save();

                $allocation->status = 'released';
                $allocation->save();

                $this->logMovement($vendorOrder->id, $allocation, 'release', $quantity, $actorUserId);
            }
        }, 3);
    }

    /**
     * Lock the order, its held allocations, then ALL affected batches in stable
     * batch-id order. Validate the sum held by this order per batch BEFORE writing.
     * This prevents partial updates when a later batch has inconsistent stock,
     * and handles several order items pointing to the same batch correctly.
     *
     * @return array{0: Collection<int, OrderItemAllocation>, 1: array<int, Batch>}
     *
     * @throws InventoryException
     */
    private function prepareLockedAllocations(VendorOrder $vendorOrder, bool $commit): array
    {
        VendorOrder::query()
            ->whereKey($vendorOrder->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $allocations = OrderItemAllocation::query()
            ->whereHas('orderItem', function (Builder $query) use ($vendorOrder): void {
                $query->where('vendor_order_id', $vendorOrder->getKey());
            })
            ->where('status', 'held')
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();

        $batches = [];

        foreach ($allocations->groupBy('batch_id')->sortKeys() as $batchId => $group) {
            $batch = Batch::query()
                ->whereKey($batchId)
                ->lockForUpdate()
                ->firstOrFail();

            // Reservation may predate expiry or quarantine. Revalidate under the
            // batch lock before shipment; release must remain possible for any status.
            if ($commit && ($batch->status !== 'active' || $batch->exp_date->toDateString() <= InventoryDate::today())) {
                throw new InventoryException("Lô {$batch->batch_no} đã hết hạn hoặc bị chặn, không thể xuất kho.");
            }

            $totalQuantity = 0;

            foreach ($group as $allocation) {
                $quantity = (int) $allocation->qty;
                $orderItemSkuId = $allocation->orderItem()->value('sku_id');

                if ($quantity <= 0) {
                    throw new InventoryException("Phân bổ {$allocation->id} có số lượng không hợp lệ.");
                }

                if ($orderItemSkuId === null || (int) $batch->sku_id !== (int) $orderItemSkuId) {
                    throw new InventoryException("Lô {$batch->id} không thuộc SKU của dòng hàng.");
                }

                $totalQuantity += $quantity;
            }

            if ((int) $batch->qty_reserved < $totalQuantity) {
                throw new InventoryException("Dữ liệu giữ chỗ không hợp lệ tại lô {$batch->id}.");
            }

            if ($commit && (int) $batch->qty_on_hand < $totalQuantity) {
                throw new InventoryException("Tồn vật lý không đủ để xuất lô {$batch->id}.");
            }

            $batches[(int) $batchId] = $batch;
        }

        return [$allocations, $batches];
    }

    private function logMovement(
        int $vendorOrderId,
        OrderItemAllocation $allocation,
        string $type,
        int $quantityDelta,
        ?int $actorUserId
    ): void {
        StockMovement::query()->create([
            'batch_id' => $allocation->batch_id,
            'type' => $type,
            // The BeautyPals migration uses a signed 'qty' column.
            'qty' => $quantityDelta,
            'ref_type' => 'vendor_order',
            'ref_id' => $vendorOrderId,
            'created_by' => $actorUserId,
        ]);
    }
}
