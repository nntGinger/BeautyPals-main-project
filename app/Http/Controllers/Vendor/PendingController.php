<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PendingController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('vendor.pending', [
            'user' => $request->user(),
            'vendor' => $request->user()->vendor,
        ]);
    }
}
