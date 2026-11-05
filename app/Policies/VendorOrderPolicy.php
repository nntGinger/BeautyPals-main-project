<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorOrder;

class VendorOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isApprovedVendor();
    }

    public function view(User $user, VendorOrder $order): bool
    {
        return $user->isAdmin() || ($user->isApprovedVendor() && $user->vendor->id === $order->vendor_id);
    }

    public function update(User $user, VendorOrder $order): bool
    {
        return $this->view($user, $order);
    }
}
