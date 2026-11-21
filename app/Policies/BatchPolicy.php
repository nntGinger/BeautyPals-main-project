<?php

namespace App\Policies;

use App\Models\Batch;
use App\Models\User;

class BatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isApprovedVendor();
    }

    public function view(User $user, Batch $batch): bool
    {
        return $user->isApprovedVendor() && $batch->sku->product->vendor_id === $user->vendor->id;
    }

    public function adjust(User $user, Batch $batch): bool
    {
        return $this->view($user, $batch);
    }
}
