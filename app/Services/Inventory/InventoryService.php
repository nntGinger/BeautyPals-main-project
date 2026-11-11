<?php

namespace App\Services\Inventory;

use App\Models\Batch;
use App\Models\OrderItemAllocation;
use App\Models\VendorOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function commitForVendorOrder(VendorOrder $vendorOrder, ?int $actorUserId = null): void
    {
        $this->process($vendorOrder, $actorUserId, true);
    }

    public function releaseForVendorOrder(VendorOrder $vendorOrder, ?int $actorUserId = null): void
    {
        $this->process($vendorOrder, $actorUserId, false);
    }

    private function process(VendorOrder $vendorOrder, ?int $actorUserId, bool $commit): void
    {
        DB::transaction(function () use ($vendorOrder, $actorUserId, $commit) {
            // Serialize processing of the same order, including standalone calls.
            VendorOrder::query()->lockForUpdate()->findOrFail($vendorOrder->id);
            $allocations = OrderItemAllocation::query()
                ->whereHas('orderItem', fn ($query) => $query->where('vendor_order_id', $vendorOrder->id))
                ->where('status', 'held')->orderBy('batch_id')->orderBy('id')->lockForUpdate()->get();

            foreach ($allocations as $allocation) {
                $batch = Batch::query()->lockForUpdate()->findOrFail($allocation->batch_id);

                if ($batch->qty_reserved < $allocation->qty || ($commit && $batch->qty_on_hand < $allocation->qty)) {
                    throw ValidationException::withMessages(['status' => 'Tồn kho và phân bổ lô không khớp. Vui lòng kiểm tra trước khi xử lý đơn.']);
                }

                $batch->qty_reserved -= $allocation->qty;
                if ($commit) {
                    $batch->qty_on_hand -= $allocation->qty;
                }
                $batch->save();
                $allocation->update(['status' => $commit ? 'committed' : 'released']);
                $batch->stockMovements()->create([
                    'type' => $commit ? 'commit' : 'release',
                    'qty' => $commit ? -$allocation->qty : $allocation->qty,
                    'ref_type' => 'vendor_order',
                    'ref_id' => $vendorOrder->id,
                    'created_by' => $actorUserId,
                ]);
            }
        }, 3);
    }
}
