<?php

namespace App\Services\Inventory;

use App\Models\Batch;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Sku;
use App\Models\StockMovement;
use App\Support\InventoryDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * S3-07: FEFO reservation by physical batch.
 *
 * Always call from CheckoutService::place()'s DB transaction. This method also
 * wraps itself in a transaction so calling it standalone cannot leave partial
 * reservations behind. MySQL/InnoDB SELECT ... FOR UPDATE serializes buyers
 * competing for the same batch.
 */
class ReservationService
{
    /**
     * @return Collection<int, OrderItemAllocation>
     *
     * @throws InventoryException
     */
    public function reserve(OrderItem $orderItem, Sku $sku, int $quantity, ?int $actorUserId = null): Collection
    {
        if ($quantity <= 0) {
            throw new InventoryException('Số lượng cần giữ chỗ phải lớn hơn 0.');
        }

        return DB::transaction(function () use ($orderItem, $sku, $quantity, $actorUserId): Collection {
            // A second checkout call must not allocate the same order line twice.
            // Lock the order item as well as the selected inventory batches.
            $lockedItem = OrderItem::query()
                ->whereKey($orderItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedItem->sku_id !== (int) $sku->getKey()) {
                throw new InventoryException('SKU không khớp với dòng hàng trong đơn.');
            }

            if ((int) $lockedItem->qty !== $quantity) {
                throw new InventoryException('Số lượng giữ chỗ phải bằng số lượng của dòng hàng.');
            }

            if (OrderItemAllocation::query()->where('order_item_id', $lockedItem->id)->exists()) {
                throw new InventoryException('Dòng hàng này đã có phân bổ tồn kho.');
            }

            // Exclude expired, quarantined, exhausted and fully reserved batches.
            // id ASC ensures deterministic selection when expiry dates tie.
            $batches = Batch::query()
                ->where('sku_id', $sku->getKey())
                ->where('status', 'active')
                ->where('exp_date', '>=', InventoryDate::tomorrow())
                ->whereRaw('qty_on_hand > qty_reserved')
                ->orderBy('exp_date', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            $remaining = $quantity;
            $allocations = collect();

            foreach ($batches as $batch) {
                $available = (int) $batch->qty_on_hand - (int) $batch->qty_reserved;
                $take = min($remaining, $available);

                if ($take <= 0) {
                    continue;
                }

                $batch->qty_reserved += $take;
                $batch->save();

                $allocation = OrderItemAllocation::query()->create([
                    'order_item_id' => $lockedItem->id,
                    'batch_id' => $batch->id,
                    'qty' => $take,
                    'status' => 'held',
                    // Deliberately NULL: Sprint 3 has no timeout-release worker.
                    // Holds remain until shipment or an explicit cancellation.
                    'expires_at' => null,
                ]);

                StockMovement::query()->create([
                    'batch_id' => $batch->id,
                    'type' => 'reserve',
                    // The actual migration uses signed "qty", NOT "quantity_delta".
                    'qty' => -$take,
                    'ref_type' => 'order_item_allocation',
                    'ref_id' => $allocation->id,
                    'created_by' => $actorUserId,
                ]);

                $allocations->push($allocation);
                $remaining -= $take;

                if ($remaining === 0) {
                    break;
                }
            }

            if ($remaining > 0) {
                throw new InventoryException(
                    "Không đủ tồn kho khả dụng cho SKU {$sku->sku_code}. Thiếu {$remaining} sản phẩm."
                );
            }

            return $allocations;
        }, 3);
    }
}
