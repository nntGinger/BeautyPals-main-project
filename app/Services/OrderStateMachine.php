<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\VendorOrder;
use Illuminate\Support\Facades\DB;

final class OrderStateMachine
{
    /**
     * Return all allowed next statuses.
     *
     * @return array<OrderStatus>
     */
    public function allowedTransitions(OrderStatus $from): array
    {
        return match ($from) {
            OrderStatus::PENDING => [
                OrderStatus::CONFIRMED,
                OrderStatus::CANCELLED,
                OrderStatus::REJECTED,
            ],

            OrderStatus::CONFIRMED => [
                OrderStatus::PACKED,
            ],

            OrderStatus::PACKED => [
                OrderStatus::SHIPPED,
            ],

            OrderStatus::SHIPPED => [
                OrderStatus::DELIVERED,
            ],

            OrderStatus::DELIVERED => [
                OrderStatus::COMPLETED,
            ],

            OrderStatus::COMPLETED,
            OrderStatus::CANCELLED,
            OrderStatus::REJECTED => [],
        };
    }

    /**
     * Check if a status transition is allowed.
     */
    public function canTransition(
        OrderStatus $from,
        OrderStatus $to
    ): bool {
        return in_array(
            $to,
            $this->allowedTransitions($from),
            true
        );
    }

    /**
     * Validate a status transition.
     */
    public function assertCanTransition(
        OrderStatus $from,
        OrderStatus $to
    ): void {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidTransitionException($from, $to);
        }
    }

    /**
     * Transition a vendor order to a new status.
     *
     * The status update and history insertion
     * must succeed or fail together.
     */
    public function transition(
        int $vendorOrderId,
        OrderStatus $to,
        ?int $changedBy = null,
        ?string $note = null,
        ?string $cancelReason = null
    ): VendorOrder {
        return DB::transaction(function () use (
            $vendorOrderId,
            $to,
            $changedBy,
            $note,
            $cancelReason
        ): VendorOrder {

            $vendorOrder = VendorOrder::query()
                ->lockForUpdate()
                ->findOrFail($vendorOrderId);

            $from = OrderStatus::from(
                $vendorOrder->status
            );

            $this->assertCanTransition($from, $to);

            $vendorOrder->status = $to->value;

            $now = now();

            switch ($to) {
                case OrderStatus::CONFIRMED:
                    $vendorOrder->confirmed_at = $now;
                    break;

                case OrderStatus::SHIPPED:
                    $vendorOrder->shipped_at = $now;
                    break;

                case OrderStatus::DELIVERED:
                    $vendorOrder->delivered_at = $now;
                    break;

                case OrderStatus::COMPLETED:
                    $vendorOrder->completed_at = $now;
                    break;

                case OrderStatus::CANCELLED:
                    $vendorOrder->cancel_reason = $cancelReason;
                    break;

                default:
                    break;
            }

            $vendorOrder->save();

            $vendorOrder->statusHistories()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'changed_by' => $changedBy,
                'note' => $note,
            ]);

            return $vendorOrder->refresh();
        });
    }
}
