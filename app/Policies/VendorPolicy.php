<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $user->isAdmin() || $user->id === $vendor->owner_user_id;
    }

    public function create(User $user): bool
    {
        return $user->isVendor() || $user->isAdmin();
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->isAdmin() || $user->id === $vendor->owner_user_id;
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return $user->isAdmin();
    }

    public function approve(User $user, Vendor $vendor): bool
    {
        return $user->isAdmin();
    }
}
