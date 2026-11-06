<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewManaged(User $user, Product $product): bool
    {
        return $user->isApprovedVendor() && $user->vendor->id === $product->vendor_id;
    }

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Product $product): bool
    {
        if ($product->status === 'approved') {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $user->isAdmin() || ($user->isApprovedVendor() && $user->vendor->id === $product->vendor_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isApprovedVendor();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isAdmin() || ($user->isApprovedVendor() && $user->vendor->id === $product->vendor_id);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    public function moderate(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }

    public function moderateAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function submit(User $user, Product $product): bool
    {
        return $this->viewManaged($user, $product);
    }
}
