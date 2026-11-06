<?php

namespace App\Policies;

use App\Models\Batch;
use App\Models\Sku;
use App\Models\User;

class BatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isApprovedVendor();
    }

    public function view(User $user, Batch $batch): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isApprovedVendor()) {
            return false;
        }

        $product = $batch->sku?->product;

        return $product !== null && (int) $product->vendor_id === (int) $user->vendor->id;
    }

    public function receive(User $user, Sku $sku): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isApprovedVendor()) {
            return false;
        }

        $product = $sku->product;

        return $product !== null && (int) $product->vendor_id === (int) $user->vendor->id;
    }

    public function update(User $user, Batch $batch): bool
    {
        return $this->view($user, $batch);
    }
}
