<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $vendor = $request->user()->vendor;
        $this->authorize('view', $vendor);

        return view('vendor.dashboard', [
            'user' => $request->user(),
            'vendor' => $vendor,
        ]);
    }
}
