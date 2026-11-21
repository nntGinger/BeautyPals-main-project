<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\VendorOrder;
use App\Services\OrderStateMachine;
use App\Services\OrderStatusService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CompleteDeliveredOrdersCommand extends Command
{
    protected $signature = 'orders:complete {--days=7}';

    protected $description = 'Complete vendor orders delivered at least the specified number of days ago';

    public function handle(OrderStateMachine $stateMachine, OrderStatusService $orderStatusService): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        if ($days === false) {
            $this->error('The --days option must be a non-negative integer.');

            return self::INVALID;
        }

        $cutoff = now()->subDays($days);
        $processed = 0;

        VendorOrder::query()
            ->where('status', OrderStatus::DELIVERED->value)
            ->where('delivered_at', '<=', $cutoff)
            ->chunkById(100, function (Collection $vendorOrders) use ($cutoff, $stateMachine, $orderStatusService, &$processed): void {
                foreach ($vendorOrders as $candidate) {
                    $completed = DB::transaction(function () use ($candidate, $cutoff, $stateMachine, $orderStatusService): bool {
                        $order = Order::query()->lockForUpdate()->findOrFail($candidate->order_id);
                        $vendorOrder = $order->vendorOrders()->lockForUpdate()->findOrFail($candidate->id);

                        // Another command may have processed the candidate while we waited for its parent lock.
                        if ($vendorOrder->status !== OrderStatus::DELIVERED->value
                            || $vendorOrder->delivered_at === null
                            || $vendorOrder->delivered_at->gt($cutoff)) {
                            return false;
                        }

                        // Automated transitions have no human actor; the state machine supports null changed_by.
                        $stateMachine->transition($vendorOrder->id, OrderStatus::COMPLETED);
                        $orderStatusService->deriveParentStatus($order);

                        return true;
                    });

                    if ($completed) {
                        $processed++;
                    }
                }
            });

        $this->info("Completed {$processed} vendor orders.");

        return self::SUCCESS;
    }
}
