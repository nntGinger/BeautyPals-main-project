<?php

namespace App\Policies;

use App\Models\Settlement;
use App\Models\User;

class SettlementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isApprovedVendor();
    }

    public function view(User $user, Settlement $settlement): bool
    {
        return $user->isAdmin() || ($user->isApprovedVendor() && $user->vendor->id === $settlement->vendor_id);
    }

    public function generate(User $user): bool
    {
        return $user->isAdmin();
    }

    public function markPaid(User $user, Settlement $settlement): bool
    {
        return $user->isAdmin();
    }
}
