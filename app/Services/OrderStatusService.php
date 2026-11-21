<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorOrder;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrderStatusService
{
    public function __construct(
        private OrderStateMachine $stateMachine,
        private InventoryService $inventoryService,
    ) {}

    public function transition(VendorOrder $vendorOrder, OrderStatus|string $targetStatus, User $actor, ?string $reason = null): VendorOrder
    {
        Gate::forUser($actor)->authorize('updateStatus', $vendorOrder);
        $target = is_string($targetStatus) ? OrderStatus::from($targetStatus) : $targetStatus;
        $reason = $reason === null ? null : trim($reason);

        if (($target === OrderStatus::REJECTED && ($reason === null || $reason === '')) || mb_strlen($reason ?? '') > 255) {
            throw ValidationException::withMessages(['reason' => 'Vui lòng nhập lý do, tối đa 255 ký tự.']);
        }

        return DB::transaction(function () use ($vendorOrder, $target, $actor, $reason): VendorOrder {
            // Lock the parent first to serialize updates from different vendors.
            $order = Order::query()->lockForUpdate()->findOrFail($vendorOrder->order_id);
            $updated = $this->stateMachine->transition(
                $vendorOrder->id, $target, $actor->id, $reason,
                $target === OrderStatus::CANCELLED ? $reason : null,
            );

            if ($target === OrderStatus::SHIPPED) {
                $this->inventoryService->commitForVendorOrder($updated, $actor->id);
            } elseif (in_array($target, [OrderStatus::CANCELLED, OrderStatus::REJECTED], true)) {
                $this->inventoryService->releaseForVendorOrder($updated, $actor->id);
            }

            $this->deriveParentStatus($order);

            return $updated;
        }, 3);
    }

    private function deriveParentStatus(Order $order): void
    {
        $statuses = $order->vendorOrders()->pluck('status');
        $closed = [OrderStatus::CANCELLED->value, OrderStatus::REJECTED->value];

        $status = match (true) {
            $statuses->every(fn ($status) => in_array($status, $closed, true)) => 'cancelled',
            $statuses->every(fn ($status) => $status === 'pending') => 'pending',
            $statuses->contains('completed') && $statuses->every(fn ($status) => in_array($status, [...$closed, 'completed'], true)) => 'completed',
            default => 'processing',
        };

        $order->update(['status' => $status]);
    }
}
