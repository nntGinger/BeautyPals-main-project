<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateVendorStatusRequest;
use App\Models\Vendor;
use App\Services\VendorStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function __construct(private readonly VendorStatusService $statusService) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Vendor::class);

        $status  = $request->query('status');
        $vendors = Vendor::with('owner')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.vendors.index', compact('vendors', 'status'));
    }

    public function show(Vendor $vendor): View
    {
        Gate::authorize('view', $vendor);

        $vendor->load(['owner', 'approver']);

        return view('admin.vendors.show', compact('vendor'));
    }

    public function updateStatus(UpdateVendorStatusRequest $request, Vendor $vendor): RedirectResponse
    {
        Gate::authorize('approve', $vendor);

        try {
            $this->statusService->transition(
                vendor:    $vendor,
                newStatus: $request->targetStatus(),
                actor:     $request->user(),
                reason:    $request->input('reason'),
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('admin.vendors.show', $vendor)
                ->withErrors(['action' => $e->getMessage()]);
        }

        return redirect()->route('admin.vendors.show', $vendor)
            ->with('success', 'Trạng thái cửa hàng đã được cập nhật.');
    }

    public function downloadLicense(Vendor $vendor): Response
    {
        Gate::authorize('view', $vendor);

        $path = $vendor->license_file;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404, 'Giấy phép không tồn tại.');
        }

        return response(
            Storage::disk('local')->get($path),
            200,
            [
                'Content-Type'        => Storage::disk('local')->mimeType($path),
                'Content-Disposition' => 'attachment; filename="' . basename($path) . '"',
            ]
        );
    }
}
