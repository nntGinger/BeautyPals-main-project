<?php

namespace App\Services;

use App\Models\Batch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatchService
{
    public function adjust(Batch $batch, int $delta, string $reason, int $actorUserId): Batch
    {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'Vui lòng nhập lý do điều chỉnh, tối đa 500 ký tự.']);
        }

        if ($delta === 0) {
            throw ValidationException::withMessages(['delta' => 'Số lượng điều chỉnh phải khác 0.']);
        }

        return DB::transaction(function () use ($batch, $delta, $reason, $actorUserId): Batch {
            $locked = Batch::query()->lockForUpdate()->findOrFail($batch->id);
            $quantity = $locked->qty_on_hand + $delta;

            if ($quantity < $locked->qty_reserved) {
                throw ValidationException::withMessages([
                    'delta' => "Tồn sau điều chỉnh không được thấp hơn số lượng đang giữ chỗ ({$locked->qty_reserved}).",
                ]);
            }

            if ($quantity > 4294967295 || $delta < -2147483648 || $delta > 2147483647) {
                throw ValidationException::withMessages(['delta' => 'Số lượng điều chỉnh vượt giới hạn cho phép.']);
            }

            $locked->update(['qty_on_hand' => $quantity]);
            $locked->stockMovements()->create([
                'type' => 'adjust',
                'qty' => $delta,
                'reason' => $reason,
                'created_by' => $actorUserId,
            ]);

            return $locked;
        }, 3);
    }
}
