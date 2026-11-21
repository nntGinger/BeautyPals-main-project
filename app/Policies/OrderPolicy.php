<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['customer', 'admin'], true);
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin() || ($user->isCustomer() && $order->customer_id === $user->id)) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    /**
     * S3-13: Customer can cancel their own order
     * only when all child VendorOrders are pending.
     */
    public function cancel(User $user, Order $order): bool
    {
        // Only the customer who placed the order.
        if (
            ! $user->isCustomer()
            || (int) $order->customer_id !== (int) $user->id
        ) {
            return false;
        }

        // An order without child orders cannot be cancelled.
        if (! $order->vendorOrders()->exists()) {
            return false;
        }

        // All VendorOrders must still be pending.
        return ! $order->vendorOrders()
            ->where('status', '!=', OrderStatus::PENDING->value)
            ->exists();
    }

    public function update(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }
}
