<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $this->authorize('viewAny', User::class);

        return view('admin.dashboard', [
            'userCount' => User::query()->count(),
            'vendorCount' => Vendor::query()->count(),
            'pendingVendors' => Vendor::query()->where('status', 'pending')->count(),
        ]);
    }
}
