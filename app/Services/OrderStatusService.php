<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorOrder;
use App\Services\Inventory\InventoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class OrderStatusService
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly InventoryService $inventoryService,
    ) {}

    public function transition(
        VendorOrder $vendorOrder,
        OrderStatus|string $targetStatus,
        User $actor,
        ?string $reason = null,
    ): VendorOrder {
        return DB::transaction(function () use ($vendorOrder, $targetStatus, $actor, $reason): VendorOrder {
            $target = is_string($targetStatus) ? OrderStatus::from($targetStatus) : $targetStatus;

            // Lock the parent first, consistently with customer cancellation and auto-completion.
            $order = Order::query()->lockForUpdate()->findOrFail($vendorOrder->order_id);
            $locked = $order->vendorOrders()->lockForUpdate()->findOrFail($vendorOrder->id);
            $updated = $this->stateMachine->transition(
                $locked->id,
                $target,
                $actor->id,
                $reason,
                $target === OrderStatus::CANCELLED ? $reason : null,
            );

            if ($target === OrderStatus::SHIPPED) {
                $this->inventoryService->commitForVendorOrder($updated, $actor->id);
            } elseif (in_array($target, [OrderStatus::CANCELLED, OrderStatus::REJECTED], true)) {
                $this->inventoryService->releaseForVendorOrder($updated, $actor->id);
            }

            $this->deriveParentStatus($order);

            return $updated;
        });
    }

    public function cancelByCustomer(Order $order, User $customer, string $reason): void
    {
        DB::transaction(function () use ($order, $customer, $reason): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->customer_id !== $customer->id) {
                throw new AuthorizationException;
            }

            $vendorOrders = $locked->vendorOrders()->orderBy('id')->lockForUpdate()->get();

            foreach ($vendorOrders as $vendorOrder) {
                if ($vendorOrder->status !== OrderStatus::PENDING->value) {
                    throw new InvalidTransitionException(OrderStatus::from($vendorOrder->status), OrderStatus::CANCELLED);
                }
            }

            foreach ($vendorOrders as $vendorOrder) {
                $this->transition($vendorOrder, OrderStatus::CANCELLED, $customer, $reason);
            }
        });
    }

    public function deriveParentStatus(Order $order): void
    {
        // Nested calls join the caller's transaction; standalone derivation also holds its locks until save.
        DB::transaction(function () use ($order): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $statuses = $locked->vendorOrders()->orderBy('id')->lockForUpdate()->pluck('status');
            $inactive = [OrderStatus::CANCELLED->value, OrderStatus::REJECTED->value];

            $locked->status = match (true) {
                $statuses->every(fn (string $status): bool => in_array($status, $inactive, true)) => 'cancelled',
                $statuses->every(fn (string $status): bool => $status === OrderStatus::PENDING->value) => 'pending',
                $statuses->contains(OrderStatus::COMPLETED->value)
                    && $statuses->every(fn (string $status): bool => in_array($status, [...$inactive, OrderStatus::COMPLETED->value], true)) => 'completed',
                default => 'processing',
            };

            $locked->save();
            $order->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
