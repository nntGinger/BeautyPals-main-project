<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\VendorOrder;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

class OrderStatusService
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * Transition a vendor order to a new status, synchronize inventory allocations,
     * and recompute the parent Order status inside a single transaction.
     */
    public function transition(
        int $vendorOrderId,
        OrderStatus|string $to,
        ?int $changedBy = null,
        ?string $note = null,
        ?string $cancelReason = null,
        ?string $trackingCode = null,
    ): VendorOrder {
        $targetStatus = is_string($to) ? OrderStatus::from($to) : $to;

        return DB::transaction(function () use (
            $vendorOrderId,
            $targetStatus,
            $changedBy,
            $note,
            $cancelReason,
            $trackingCode,
        ): VendorOrder {
            $vendorOrder = $this->stateMachine->transition(
                vendorOrderId: $vendorOrderId,
                to: $targetStatus,
                changedBy: $changedBy,
                note: $note,
                cancelReason: $cancelReason,
            );

            if ($targetStatus === OrderStatus::REJECTED && $cancelReason !== null) {
                $vendorOrder->update(['cancel_reason' => $cancelReason]);
            }

            if ($targetStatus === OrderStatus::SHIPPED) {
                if ($trackingCode !== null && trim($trackingCode) !== '') {
                    $vendorOrder->update(['tracking_code' => trim($trackingCode)]);
                }

                $this->inventoryService->commitForVendorOrder($vendorOrder->id, $changedBy);
            }

            if (in_array($targetStatus, [OrderStatus::CANCELLED, OrderStatus::REJECTED], true)) {
                $this->inventoryService->releaseForVendorOrder($vendorOrder->id, $changedBy);
            }

            $this->syncParentOrder((int) $vendorOrder->order_id);

            return $vendorOrder->fresh(['items.allocations', 'statusHistories', 'order']);
        });
    }

    /**
     * Derive and persist the parent Order status from its child VendorOrders.
     */
    public function syncParentOrder(int $orderId): Order
    {
        return DB::transaction(function () use ($orderId): Order {
            $order = Order::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            $statuses = VendorOrder::query()
                ->where('order_id', $order->id)
                ->pluck('status')
                ->all();

            if ($statuses === []) {
                return $order;
            }

            $derivedStatus = $this->deriveParentStatus($statuses);

            $paymentStatus = $order->payment_status;
            if ($derivedStatus === 'cancelled') {
                $paymentStatus = 'failed';
            } elseif ($derivedStatus === 'completed') {
                $paymentStatus = 'paid';
            } else {
                $activeStatuses = array_values(array_filter(
                    $statuses,
                    fn (string $s) => ! in_array($s, ['cancelled', 'rejected'], true),
                ));
                if (
                    $activeStatuses !== []
                    && count(array_intersect($activeStatuses, ['delivered', 'completed'])) === count($activeStatuses)
                ) {
                    $paymentStatus = 'paid';
                }
            }

            $order->update([
                'status' => $derivedStatus,
                'payment_status' => $paymentStatus,
            ]);

            if ($paymentStatus === 'paid') {
                $order->payments()
                    ->where('status', '!=', 'paid')
                    ->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);
            } elseif ($paymentStatus === 'failed') {
                $order->payments()
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'failed',
                    ]);
            }

            return $order->refresh();
        });
    }

    /**
     * Compute parent order status from child vendor order statuses.
     *
     * Rules (S3-10):
     * - All vendor orders in [cancelled, rejected] -> 'cancelled'
     * - All active (non-cancelled/rejected) vendor orders are 'completed' -> 'completed'
     * - All vendor orders are 'pending' -> 'pending'
     * - Otherwise -> 'processing'
     *
     * @param  list<string>  $statuses
     */
    public function deriveParentStatus(array $statuses): string
    {
        $activeStatuses = array_values(array_filter(
            $statuses,
            fn (string $s) => ! in_array($s, ['cancelled', 'rejected'], true),
        ));

        if ($activeStatuses === []) {
            return 'cancelled';
        }

        $allCompleted = count(array_filter(
            $activeStatuses,
            fn (string $s) => $s === 'completed',
        )) === count($activeStatuses);

        if ($allCompleted) {
            return 'completed';
        }

        $allPending = count(array_filter(
            $statuses,
            fn (string $s) => $s === 'pending',
        )) === count($statuses);

        if ($allPending) {
            return 'pending';
        }

        return 'processing';
    }

    /**
     * Complete all vendor orders that have been in 'delivered' status for at least $days days.
     */
    public function autoCompleteDeliveredOrders(int $days = 7): int
    {
        $cutoff = now()->subDays($days);

        $dueIds = VendorOrder::query()
            ->where('status', OrderStatus::DELIVERED->value)
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', $cutoff)
            ->pluck('id');

        $count = 0;

        foreach ($dueIds as $id) {
            $this->transition(
                vendorOrderId: (int) $id,
                to: OrderStatus::COMPLETED,
                changedBy: null,
                note: "Tự động hoàn tất sau {$days} ngày giao hàng thành công.",
            );
            $count++;
        }

        return $count;
    }
}
