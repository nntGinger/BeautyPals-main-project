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
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isApprovedVendor()
            && (int) $user->vendor->id === (int) $settlement->vendor_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Settlement $settlement): bool
    {
        return $user->isAdmin();
    }
}
