<?php

namespace App\Services\Inventory;

use App\Exceptions\InventoryException;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Sku;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BatchService
{
    /**
     * Receive a new inventory batch for a SKU inside a transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function receive(User $actor, array $data): Batch
    {
        $skuId = (int) ($data['sku_id'] ?? 0);
        $sku = Sku::query()->with('product')->findOrFail($skuId);

        Gate::forUser($actor)->authorize('receive', [Batch::class, $sku]);

        $today = Carbon::today()->toDateString();

        $validated = Validator::make(
            [
                'sku_id' => $skuId,
                'batch_no' => is_string($data['batch_no'] ?? null) ? trim($data['batch_no']) : ($data['batch_no'] ?? null),
                'mfg_date' => $data['mfg_date'] ?? null,
                'exp_date' => $data['exp_date'] ?? null,
                'qty' => $data['qty'] ?? $data['qty_received'] ?? null,
            ],
            [
                'sku_id' => ['required', 'integer', 'exists:skus,id'],
                'batch_no' => [
                    'required',
                    'string',
                    'max:60',
                    Rule::unique('batches', 'batch_no')->where(fn ($q) => $q->where('sku_id', $skuId)),
                ],
                'mfg_date' => ['required', 'date'],
                'exp_date' => ['required', 'date', 'after:mfg_date', 'after:'.$today],
                'qty' => ['required', 'integer', 'min:1', 'max:1000000'],
            ],
            [
                'batch_no.required' => 'Vui lòng nhập mã lô hàng.',
                'batch_no.unique' => 'Mã lô này đã tồn tại trong cùng SKU.',
                'mfg_date.required' => 'Vui lòng nhập ngày sản xuất.',
                'exp_date.required' => 'Vui lòng nhập hạn sử dụng.',
                'exp_date.after' => 'Hạn sử dụng phải sau ngày sản xuất và còn hạn.',
                'qty.required' => 'Vui lòng nhập số lượng nhập kho.',
                'qty.min' => 'Số lượng nhập kho phải lớn hơn 0.',
            ],
        )->validate();

        $qty = (int) $validated['qty'];

        return DB::transaction(function () use ($actor, $sku, $validated, $qty): Batch {
            Sku::query()->whereKey($sku->id)->lockForUpdate()->firstOrFail();

            $batch = Batch::query()->create([
                'sku_id' => $sku->id,
                'batch_no' => $validated['batch_no'],
                'mfg_date' => $validated['mfg_date'],
                'exp_date' => $validated['exp_date'],
                'qty_received' => $qty,
                'qty_on_hand' => $qty,
                'qty_reserved' => 0,
                'status' => 'active',
            ]);

            StockMovement::query()->create([
                'batch_id' => $batch->id,
                'type' => 'receive',
                'qty' => $qty,
                'ref_type' => 'batch',
                'ref_id' => $batch->id,
                'created_by' => $actor->id,
                'created_at' => now(),
            ]);

            return $batch->refresh();
        });
    }

    /**
     * Manually adjust a batch's on-hand quantity with a required reason and audit trail.
     */
    public function adjust(User $actor, int $batchId, int $deltaQty, string $reason): Batch
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Vui lòng nhập lý do điều chỉnh tồn kho.',
            ]);
        }

        if ($deltaQty === 0) {
            throw ValidationException::withMessages([
                'delta_qty' => 'Số lượng điều chỉnh phải khác 0.',
            ]);
        }

        return DB::transaction(function () use ($actor, $batchId, $deltaQty, $reason): Batch {
            $batch = Batch::query()
                ->with('sku.product')
                ->whereKey($batchId)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('update', $batch);

            $oldOnHand = (int) $batch->qty_on_hand;
            $newOnHand = $oldOnHand + $deltaQty;

            if ($newOnHand < 0 || $newOnHand < (int) $batch->qty_reserved) {
                throw InventoryException::negativeStockNotAllowed($batch->id);
            }

            $batch->update([
                'qty_on_hand' => $newOnHand,
            ]);

            StockMovement::query()->create([
                'batch_id' => $batch->id,
                'type' => 'adjustment',
                'qty' => $deltaQty,
                'ref_type' => 'batch_adjustment',
                'ref_id' => $batch->id,
                'created_by' => $actor->id,
                'created_at' => now(),
            ]);

            AuditLog::query()->create([
                'actor_id' => $actor->id,
                'action' => 'batch.adjust',
                'entity_type' => 'batch',
                'entity_id' => $batch->id,
                'old_values' => ['qty_on_hand' => $oldOnHand],
                'new_values' => ['qty_on_hand' => $newOnHand, 'delta_qty' => $deltaQty, 'reason' => $reason],
                'ip' => app()->runningInConsole() ? null : request()->ip(),
            ]);

            return $batch->refresh();
        });
    }
}
