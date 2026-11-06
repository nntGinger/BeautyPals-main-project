<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Settlement;
use App\Models\SettlementLine;
use App\Models\VendorOrder;
use App\Observers\AuditableObserver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettlementService
{
    /**
     * Generate or update settlements for all completed, unsettled vendor orders within [$periodStart, $periodEnd].
     *
     * Rules (S4-08):
     * - Filters vendor_orders where status = 'completed', completed_at in period, and not in settlement_lines.
     * - gross = SUM(order_items.line_total)
     * - commission = captured commission_amount
     * - net = gross - commission
     * - Idempotent: running twice for the same period does not duplicate lines or double-count totals.
     *
     * @return Collection<int, Settlement>
     */
    public function generate(
        string $periodStart,
        string $periodEnd,
        ?int $finalizedBy = null,
    ): Collection {
        $startDate = Carbon::parse($periodStart)->startOfDay();
        $endDate = Carbon::parse($periodEnd)->endOfDay();

        if ($endDate->lt($startDate)) {
            throw ValidationException::withMessages([
                'period_end' => 'Ngày kết thúc kỳ đối soát phải sau hoặc bằng ngày bắt đầu.',
            ]);
        }

        $startStr = $startDate->toDateString();
        $endStr = $endDate->toDateString();

        return DB::transaction(function () use (
            $startDate,
            $endDate,
            $startStr,
            $endStr,
            $finalizedBy,
        ): Collection {
            $eligibleOrders = VendorOrder::query()
                ->where('status', OrderStatus::COMPLETED->value)
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->whereDoesntHave('settlementLine')
                ->with('items')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            $byVendor = $eligibleOrders->groupBy('vendor_id');
            $touchedSettlementIds = [];

            foreach ($byVendor as $vendorId => $vendorOrders) {
                $settlement = AuditableObserver::withoutAuditing(fn () => Settlement::query()->firstOrCreate(
                    [
                        'vendor_id' => (int) $vendorId,
                        'period_start' => $startStr,
                        'period_end' => $endStr,
                    ],
                    [
                        'gross_sales' => 0,
                        'commission_total' => 0,
                        'refund_total' => 0,
                        'adjustment' => 0,
                        'net_payout' => 0,
                        'status' => 'finalized',
                        'finalized_by' => $finalizedBy,
                    ],
                ));

                $settlement = Settlement::query()
                    ->whereKey($settlement->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                foreach ($vendorOrders as $vo) {
                    if (SettlementLine::query()->where('vendor_order_id', $vo->id)->exists()) {
                        continue;
                    }

                    $gross = (int) $vo->items->sum('line_total');
                    $commission = (int) $vo->items->sum('commission_amount');
                    $net = $gross - $commission;

                    SettlementLine::query()->create([
                        'settlement_id' => $settlement->id,
                        'vendor_order_id' => $vo->id,
                        'gross' => $gross,
                        'commission' => $commission,
                        'refund' => 0,
                        'net' => $net,
                    ]);
                }

                $totals = SettlementLine::query()
                    ->where('settlement_id', $settlement->id)
                    ->selectRaw('COALESCE(SUM(gross), 0) as gross_sales, COALESCE(SUM(commission), 0) as commission_total, COALESCE(SUM(refund), 0) as refund_total')
                    ->first();

                $grossSales = (int) ($totals->gross_sales ?? 0);
                $commissionTotal = (int) ($totals->commission_total ?? 0);
                $refundTotal = (int) ($totals->refund_total ?? 0);
                $adjustment = (int) $settlement->adjustment;
                $netPayout = $grossSales - $commissionTotal - $refundTotal + $adjustment;

                AuditableObserver::withoutAuditing(fn () => $settlement->update([
                    'gross_sales' => $grossSales,
                    'commission_total' => $commissionTotal,
                    'refund_total' => $refundTotal,
                    'net_payout' => $netPayout,
                    'status' => $settlement->status === 'paid' ? 'paid' : 'finalized',
                    'finalized_by' => $finalizedBy ?? $settlement->finalized_by,
                ]));

                AuditLog::query()->create([
                    'actor_id' => $finalizedBy,
                    'action' => 'settlement.finalized',
                    'entity_type' => 'settlement',
                    'entity_id' => $settlement->id,
                    'old_values' => null,
                    'new_values' => [
                        'vendor_id' => (int) $vendorId,
                        'period_start' => $startStr,
                        'period_end' => $endStr,
                        'gross_sales' => $grossSales,
                        'commission_total' => $commissionTotal,
                        'net_payout' => $netPayout,
                    ],
                    'ip' => app()->runningInConsole() ? null : request()->ip(),
                ]);

                $touchedSettlementIds[] = $settlement->id;
            }

            if ($touchedSettlementIds === []) {
                return Settlement::query()
                    ->where('period_start', $startStr)
                    ->where('period_end', $endStr)
                    ->with('lines.vendorOrder')
                    ->orderBy('vendor_id')
                    ->get();
            }

            return Settlement::query()
                ->whereIn('id', $touchedSettlementIds)
                ->with('lines.vendorOrder')
                ->orderBy('vendor_id')
                ->get();
        });
    }

    /**
     * Mark a settlement as paid.
     */
    public function markPaid(int $settlementId, int $actorId): Settlement
    {
        return DB::transaction(function () use ($settlementId, $actorId): Settlement {
            $settlement = Settlement::query()
                ->whereKey($settlementId)
                ->lockForUpdate()
                ->firstOrFail();

            $oldStatus = $settlement->status;

            AuditableObserver::withoutAuditing(fn () => $settlement->update([
                'status' => 'paid',
                'paid_at' => $settlement->paid_at ?? now(),
                'finalized_by' => $settlement->finalized_by ?? $actorId,
            ]));

            AuditLog::query()->create([
                'actor_id' => $actorId,
                'action' => 'settlement.paid',
                'entity_type' => 'settlement',
                'entity_id' => $settlement->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => 'paid', 'paid_at' => $settlement->paid_at?->toIso8601String()],
                'ip' => app()->runningInConsole() ? null : request()->ip(),
            ]);

            return $settlement->refresh();
        });
    }
}
