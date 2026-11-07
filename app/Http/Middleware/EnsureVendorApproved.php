<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'vendor') {
            abort(403, 'Bạn không có quyền truy cập khu vực cửa hàng.');
        }

        $vendor = $user->vendor;

        if (! $vendor || $vendor->status !== 'approved') {
            // Redirect to the pending/status explanation page instead of a bare 403.
            return redirect()->route('vendor.pending');
        }

        return $next($request);
    }
}
