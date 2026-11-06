<?php

namespace App\Policies;

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

    public function update(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->isAdmin() || ($user->isCustomer() && (int) $order->customer_id === (int) $user->id);
    }
}
