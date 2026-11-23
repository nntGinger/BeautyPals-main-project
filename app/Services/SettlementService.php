<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Settlement;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    public function generate(string|Carbon $periodStart, string|Carbon $periodEnd, ?int $vendorId = null): Collection
    {
        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->endOfDay();

        return DB::transaction(function () use ($start, $end, $vendorId) {
            $eligibleOrders = VendorOrder::query()
                ->where('status', OrderStatus::COMPLETED->value)
                ->whereBetween('completed_at', [$start, $end])
                ->when($vendorId !== null, fn (Builder $query) => $query->where('vendor_id', $vendorId));

            // Serialize generation for each vendor, even when the settlement does not exist yet.
            $vendorIds = Vendor::query()
                ->whereIn('id', (clone $eligibleOrders)->doesntHave('settlementLine')->select('vendor_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            $settlements = collect();

            foreach ($vendorIds as $id) {
                $orders = (clone $eligibleOrders)
                    ->where('vendor_id', $id)
                    ->whereDoesntHave('settlementLine', fn (Builder $query) => $query->lockForUpdate())
                    ->lockForUpdate()
                    ->get();

                if ($orders->isEmpty()) {
                    continue;
                }

                $period = [
                    'vendor_id' => $id,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                ];
                $settlement = Settlement::where('vendor_id', $id)
                    ->whereDate('period_start', $period['period_start'])
                    ->whereDate('period_end', $period['period_end'])
                    ->lockForUpdate()
                    ->first()
                    ?? Settlement::create($period + ['status' => 'pending']);

                if ($settlement->status !== 'pending') {
                    continue;
                }

                foreach ($orders as $order) {
                    $settlement->lines()->create([
                        'vendor_order_id' => $order->id,
                        'gross' => $order->subtotal,
                        'commission' => $order->commission_amount,
                        'net' => $order->subtotal - $order->commission_amount,
                    ]);
                }

                $lines = $settlement->lines()->lockForUpdate()->get();
                $grossSales = $lines->sum('gross');
                $commissionTotal = $lines->sum('commission');
                $netPayable = max(0, $grossSales - $commissionTotal);
                $settlement->update([
                    'gross_sales' => $grossSales,
                    'commission_total' => $commissionTotal,
                    'net_payable' => $netPayable,
                    'net_payout' => $netPayable,
                ]);

                if ($settlement->wasRecentlyCreated) {
                    $this->writeAudit($settlement, 'settlement.generate', null, $settlement->only([
                        'status', 'vendor_id', 'period_start', 'period_end', 'gross_sales', 'commission_total', 'net_payable',
                    ]));
                }

                $settlements->push($settlement);
            }

            return $settlements;
        }, 2);
    }

    public function markPaid(Settlement $settlement): Settlement
    {
        return DB::transaction(function () use ($settlement) {
            $locked = Settlement::whereKey($settlement->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === 'pending') {
                $old = $locked->only(['status', 'paid_at']);
                $locked->update(['status' => 'paid', 'paid_at' => now()]);
                $this->writeAudit($locked, 'settlement.mark_paid', $old, $locked->only(['status', 'paid_at']));
            }

            return $locked;
        }, 2);
    }

    private function writeAudit(Settlement $settlement, string $action, ?array $old, array $new): void
    {
        AuditLog::create([
            'actor_id' => auth()->id(),
            'action' => $action,
            'entity_type' => 'settlement',
            'entity_id' => $settlement->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip' => request()->ip(),
        ]);
    }
}
